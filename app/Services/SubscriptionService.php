<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\SubscriptionException;
use App\Models\Administration\Company;
use App\Models\Platform\Package;
use App\Models\Platform\Subscription;
use App\Models\Platform\SubscriptionInvoice;
use App\Models\Platform\SubscriptionPayment;
use App\Support\Modules;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Carbon;

/**
 * What a company's subscription allows (phase 10), and the platform admin's
 * actions on it.
 *
 * A company with no subscription is not limited: everything that existed
 * before subscriptions keeps working as it did, and the platform admin brings
 * a company under a package when it is sold one.
 */
class SubscriptionService
{
    public function forCompany(Company|string|null $company): ?Subscription
    {
        $id = $company instanceof Company ? $company->id : $company;

        return $id ? Subscription::query()->with('package')->where('company_id', $id)->first() : null;
    }

    /**
     * The modules the package includes, or null when nothing limits them
     * (no subscription).
     *
     * @return list<string>|null
     */
    public function allowedModules(Company|string|null $company): ?array
    {
        $subscription = $this->forCompany($company);

        return $subscription ? array_values((array) $subscription->package?->modules) : null;
    }

    /**
     * What the page banner needs: the state and the date, when it is worth a
     * warning (expiring, expired, blocked). Null when the subscription is
     * healthy or the company has none.
     *
     * @return array{state: string, ends_on: string, days_left: int, reason: ?string}|null
     */
    public function banner(Company|string|null $company): ?array
    {
        $subscription = $this->forCompany($company);

        if (! $subscription || $subscription->state() === 'active') {
            return null;
        }

        return [
            'state' => $subscription->state(),
            'ends_on' => $subscription->ends_on->toDateString(),
            'ends_on_display' => app(DateConversionService::class)->toDisplay($subscription->ends_on->toDateString()),
            'days_left' => $subscription->daysLeft(),
            'reason' => $subscription->isBlocked() ? $subscription->block_reason : null,
        ];
    }

    /** @return array{users: int, branches: int} */
    public function usage(Company|string $company): array
    {
        $id = $company instanceof Company ? $company->id : $company;

        return [
            'users' => DB::table('users')->where('company_id', $id)
                ->where('status', UserStatus::ACTIVE->value)->whereNull('deleted_at')->count(),
            'branches' => DB::table('branches')->where('company_id', $id)->whereNull('deleted_at')->count(),
        ];
    }

    /** Refuses a new user when the package's user limit is reached. */
    public function assertCanAddUser(Company|string|null $company): void
    {
        $this->assertWithinLimit($company, 'max_users', 'users', 'messages.subscription.user_limit');
    }

    /** Refuses a new branch when the package's branch limit is reached. */
    public function assertCanAddBranch(Company|string|null $company): void
    {
        $this->assertWithinLimit($company, 'max_branches', 'branches', 'messages.subscription.branch_limit');
    }

    private function assertWithinLimit(Company|string|null $company, string $limitColumn, string $usageKey, string $message): void
    {
        $subscription = $this->forCompany($company);
        $limit = $subscription?->package?->{$limitColumn};

        if ($limit === null) {
            return;
        }

        $id = $company instanceof Company ? $company->id : $company;

        if ($this->usage($id)[$usageKey] >= $limit) {
            throw new SubscriptionException(__($message, [
                'limit' => $limit,
                'package' => $subscription->package->name,
            ]));
        }
    }

    // --- The platform admin's actions --------------------------------------

    /** Puts a company on a package, or changes its package or dates. */
    public function assign(Company $company, Package $package, Carbon $startsOn, Carbon $endsOn, ?string $notes = null): Subscription
    {
        return Subscription::query()->updateOrCreate(
            ['company_id' => $company->id],
            [
                'package_id' => $package->id,
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $endsOn->toDateString(),
                'notes' => $notes,
                'created_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ],
        );
    }

    /**
     * Extends by the package's period (or the given months), from the end of
     * the current period, or from today when it already ended: paying late does
     * not buy the days that were lost, and paying early loses none.
     */
    public function renew(Subscription $subscription, ?int $months = null): Subscription
    {
        $months ??= (int) ($subscription->package?->period_months ?: 12);
        $from = $subscription->ends_on->isFuture() ? $subscription->ends_on->copy() : now()->startOfDay();

        $subscription->update([
            'ends_on' => $from->addMonthsNoOverflow($months)->toDateString(),
            'updated_by' => Auth::id(),
        ]);

        return $subscription->refresh();
    }

    public function block(Subscription $subscription, ?string $reason = null): Subscription
    {
        $subscription->update(['blocked_at' => now(), 'block_reason' => $reason, 'updated_by' => Auth::id()]);

        return $subscription->refresh();
    }

    public function unblock(Subscription $subscription): Subscription
    {
        $subscription->update(['blocked_at' => null, 'block_reason' => null, 'updated_by' => Auth::id()]);

        return $subscription->refresh();
    }

    /** SUB-2026-0007: a number per year, counted over every company. */
    public function nextInvoiceNumber(?Carbon $on = null): string
    {
        $year = ($on ?? now())->year;
        $prefix = "SUB-{$year}-";

        $last = SubscriptionInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        return $prefix.str_pad((string) (($last ? (int) substr($last, strlen($prefix)) : 0) + 1), 4, '0', STR_PAD_LEFT);
    }

    /** An invoice for a subscription period; the package's price unless told otherwise. */
    public function invoice(Subscription $subscription, array $data): SubscriptionInvoice
    {
        $package = $subscription->package;

        return SubscriptionInvoice::query()->create([
            'number' => $this->nextInvoiceNumber(Carbon::parse($data['issued_on'])),
            'company_id' => $subscription->company_id,
            'subscription_id' => $subscription->id,
            'issued_on' => $data['issued_on'],
            'due_on' => $data['due_on'] ?? null,
            'period_from' => $data['period_from'] ?? null,
            'period_to' => $data['period_to'] ?? null,
            'amount' => $data['amount'] ?? $package?->price ?? 0,
            'currency' => $data['currency'] ?? $package?->currency ?? 'AFN',
            'description' => $data['description'] ?? null,
            'status' => 'open',
            'created_by' => Auth::id(),
        ]);
    }

    public function recordPayment(SubscriptionInvoice $invoice, array $data): SubscriptionPayment
    {
        return DB::transaction(function () use ($invoice, $data) {
            $invoice->loadMissing('payments');

            if ($invoice->status === 'void') {
                throw new SubscriptionException(__('messages.subscription.invoice_void'));
            }

            if (bccomp((string) $data['amount'], $invoice->balance(), 4) > 0) {
                throw new SubscriptionException(__('messages.subscription.overpayment', ['balance' => $invoice->balance()]));
            }

            return $invoice->payments()->create([
                'company_id' => $invoice->company_id,
                'amount' => $data['amount'],
                'paid_on' => $data['paid_on'],
                'method' => $data['method'] ?? 'cash',
                'reference' => $data['reference'] ?? null,
                'note' => $data['note'] ?? null,
                'created_by' => Auth::id(),
            ]);
        });
    }

    /** Modules a package can offer: the ones that exist (config/modules.php). */
    public function offerableModules(): array
    {
        return Modules::available();
    }
}
