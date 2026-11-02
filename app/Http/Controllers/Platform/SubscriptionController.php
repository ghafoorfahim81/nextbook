<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\Administration\Company;
use App\Models\Platform\Package;
use App\Models\Platform\Subscription;
use App\Models\Platform\SubscriptionInvoice;
use App\Models\Platform\SubscriptionPayment;
use App\Services\DateConversionService;
use App\Services\SubscriptionService;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * The platform admin's view of every company's subscription (phase 10): who is
 * on which package, until when, how much of it they use, and the invoices and
 * payments. Behind EnsurePlatformAdmin.
 *
 * The company comes in as a plain id and is looked up without its global
 * scope: the scope narrows a company to the ones the signed-in user works in,
 * and this panel is for all of them.
 */
class SubscriptionController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly DateConversionService $dates,
    ) {
    }

    public function index(Request $request)
    {
        $search = trim((string) $request->query('search', ''));

        $companies = Company::query()->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->when($search !== '', fn ($query) => $query->where(function ($inner) use ($search) {
                $inner->where('name_en', 'ilike', "%{$search}%")
                    ->orWhere('name_fa', 'ilike', "%{$search}%")
                    ->orWhere('name_pa', 'ilike', "%{$search}%");
            }))
            ->orderBy('name_en')
            ->paginate(25)
            ->withQueryString();

        $ids = $companies->pluck('id');
        $subscriptions = Subscription::query()->with('package')->whereIn('company_id', $ids)->get()->keyBy('company_id');
        $users = DB::table('users')->whereIn('company_id', $ids)->where('status', 'active')->whereNull('deleted_at')
            ->selectRaw('company_id, count(*) as n')->groupBy('company_id')->pluck('n', 'company_id');
        $branches = DB::table('branches')->whereIn('company_id', $ids)->whereNull('deleted_at')
            ->selectRaw('company_id, count(*) as n')->groupBy('company_id')->pluck('n', 'company_id');

        return inertia('Platform/Subscriptions/Index', [
            'companies' => $companies->through(function (Company $company) use ($subscriptions, $users, $branches) {
                $subscription = $subscriptions->get($company->id);

                return [
                    'id' => $company->id,
                    'name' => $company->localizedName(),
                    'business_type' => $company->business_type?->value,
                    'package' => $subscription?->package?->name,
                    'state' => $subscription?->state() ?? 'none',
                    'ends_on' => $subscription ? $this->dates->toDisplay($subscription->ends_on->toDateString()) : null,
                    'days_left' => $subscription?->daysLeft(),
                    'users' => (int) ($users[$company->id] ?? 0),
                    'max_users' => $subscription?->package?->max_users,
                    'branches' => (int) ($branches[$company->id] ?? 0),
                    'max_branches' => $subscription?->package?->max_branches,
                ];
            }),
            'filters' => ['search' => $search],
            'totals' => [
                'companies' => Company::query()->withoutGlobalScopes()->whereNull('deleted_at')->count(),
                'subscribed' => Subscription::query()->count(),
                'expiring' => Subscription::query()->get()->filter(fn (Subscription $s) => $s->state() === 'expiring')->count(),
                'blocked_or_expired' => Subscription::query()->get()->filter(fn (Subscription $s) => in_array($s->state(), ['blocked', 'expired'], true))->count(),
            ],
        ]);
    }

    public function show(string $company)
    {
        $company = $this->company($company);
        $subscription = $this->subscriptions->forCompany($company);

        return inertia('Platform/Subscriptions/Show', [
            'company' => [
                'id' => $company->id,
                'name' => $company->localizedName(),
                'business_type' => $company->business_type?->value,
            ],
            'plan' => $subscription ? [
                'package_id' => $subscription->package_id,
                'package' => $subscription->package?->name,
                'starts_on' => $this->dates->toDisplay($subscription->starts_on->toDateString()),
                'ends_on' => $this->dates->toDisplay($subscription->ends_on->toDateString()),
                'state' => $subscription->state(),
                'days_left' => $subscription->daysLeft(),
                'blocked_at' => $subscription->blocked_at?->toDateTimeString(),
                'block_reason' => $subscription->block_reason,
                'notes' => $subscription->notes,
            ] : null,
            'usage' => $this->subscriptions->usage($company),
            'packages' => Package::query()->where('is_active', true)
                ->orWhere('id', $subscription?->package_id)
                ->orderBy('price')->get(['id', 'name', 'price', 'currency', 'period_months', 'max_users', 'max_branches', 'modules']),
            'invoices' => $subscription
                ? SubscriptionInvoice::query()->with('payments')->where('company_id', $company->id)
                    ->orderByDesc('issued_on')->orderByDesc('number')->get()
                    ->map(fn (SubscriptionInvoice $invoice) => $this->invoiceRow($invoice))
                : [],
            'methods' => SubscriptionPayment::METHODS,
            'moduleNames' => Modules::available(),
        ]);
    }

    /** Puts the company on a package, or changes its package or dates. */
    public function update(Request $request, string $company)
    {
        $company = $this->company($company);

        $data = $request->validate([
            'package_id' => ['required', 'string', Rule::exists('packages', 'id')->whereNull('deleted_at')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date'],
            'notes' => ['nullable', 'string'],
        ]);

        $startsOn = Carbon::parse($this->dates->toGregorian($data['starts_on']));
        $endsOn = Carbon::parse($this->dates->toGregorian($data['ends_on']));

        if ($endsOn->lt($startsOn)) {
            return back()->withErrors(['ends_on' => __('messages.subscription.ends_before_start')]);
        }

        $this->subscriptions->assign($company, Package::query()->findOrFail($data['package_id']), $startsOn, $endsOn, $data['notes'] ?? null);

        return back()->with('success', __('general.updated_successfully', ['resource' => __('general.resource.subscription')]));
    }

    /** Extends by the package's period, optionally invoicing the new period at once. */
    public function renew(Request $request, string $company)
    {
        $subscription = $this->subscriptionOf($company);

        $data = $request->validate([
            'months' => ['nullable', 'integer', 'min:1', 'max:60'],
            'invoice' => ['boolean'],
        ]);

        $previousEnd = $subscription->ends_on->isFuture() ? $subscription->ends_on->copy() : now()->startOfDay();

        DB::transaction(function () use ($subscription, $data, $previousEnd) {
            $subscription = $this->subscriptions->renew($subscription, $data['months'] ?? null);

            if (! empty($data['invoice'])) {
                $months = $data['months'] ?? (int) $subscription->package->period_months;
                $this->subscriptions->invoice($subscription, [
                    'issued_on' => now()->toDateString(),
                    'due_on' => now()->addDays(14)->toDateString(),
                    'period_from' => $previousEnd->toDateString(),
                    'period_to' => $subscription->ends_on->toDateString(),
                    // The package's price is for one period; a longer or
                    // shorter renewal is pro-rata.
                    'amount' => bcmul((string) $subscription->package->price, bcdiv((string) $months, (string) max(1, $subscription->package->period_months), 6), 4),
                ]);
            }
        });

        return back()->with('success', __('messages.subscription.renewed'));
    }

    public function block(Request $request, string $company)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:255']]);
        $this->subscriptions->block($this->subscriptionOf($company), $data['reason'] ?? null);

        return back()->with('success', __('messages.subscription.blocked_done'));
    }

    public function unblock(string $company)
    {
        $this->subscriptions->unblock($this->subscriptionOf($company));

        return back()->with('success', __('messages.subscription.unblocked_done'));
    }

    // --- Invoices and payments ---------------------------------------------

    public function storeInvoice(Request $request, string $company)
    {
        $subscription = $this->subscriptionOf($company);

        $data = $request->validate([
            'issued_on' => ['required', 'date'],
            'due_on' => ['nullable', 'date'],
            'period_from' => ['nullable', 'date'],
            'period_to' => ['nullable', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'currency' => ['required', 'string', 'size:3'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);

        foreach (['issued_on', 'due_on', 'period_from', 'period_to'] as $field) {
            if (! empty($data[$field])) {
                $data[$field] = $this->dates->toGregorian($data[$field]);
            }
        }
        $data['currency'] = strtoupper($data['currency']);

        $this->subscriptions->invoice($subscription, $data);

        return back()->with('success', __('general.created_successfully', ['resource' => __('general.resource.invoice')]));
    }

    public function voidInvoice(string $company, string $invoice)
    {
        $model = SubscriptionInvoice::query()->where('company_id', $company)->findOrFail($invoice);
        $model->update(['status' => 'void']);

        return back()->with('success', __('messages.subscription.invoice_voided'));
    }

    public function storePayment(Request $request, string $company, string $invoice)
    {
        $model = SubscriptionInvoice::query()->with('payments')->where('company_id', $company)->findOrFail($invoice);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0.01'],
            'paid_on' => ['required', 'date'],
            'method' => ['required', Rule::in(SubscriptionPayment::METHODS)],
            'reference' => ['nullable', 'string', 'max:255'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $data['paid_on'] = $this->dates->toGregorian($data['paid_on']);

        $this->subscriptions->recordPayment($model, $data);

        return back()->with('success', __('messages.subscription.payment_recorded'));
    }

    public function destroyPayment(string $company, string $payment)
    {
        SubscriptionPayment::query()->where('company_id', $company)->findOrFail($payment)->delete();

        return back()->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.payment')]));
    }

    // --- Helpers -----------------------------------------------------------

    private function company(string $id): Company
    {
        return Company::query()->withoutGlobalScopes()->whereNull('deleted_at')->findOrFail($id);
    }

    private function subscriptionOf(string $companyId): Subscription
    {
        return $this->subscriptions->forCompany($this->company($companyId)) ?? abort(404);
    }

    private function invoiceRow(SubscriptionInvoice $invoice): array
    {
        return [
            'id' => $invoice->id,
            'number' => $invoice->number,
            'issued_on' => $this->dates->toDisplay($invoice->issued_on->toDateString()),
            'due_on' => $invoice->due_on ? $this->dates->toDisplay($invoice->due_on->toDateString()) : null,
            'period' => $invoice->period_from && $invoice->period_to
                ? $this->dates->toDisplay($invoice->period_from->toDateString()).' → '.$this->dates->toDisplay($invoice->period_to->toDateString())
                : null,
            'amount' => (float) $invoice->amount,
            'currency' => $invoice->currency,
            'description' => $invoice->description,
            'state' => $invoice->paymentState(),
            'paid' => (float) $invoice->paidAmount(),
            'balance' => (float) $invoice->balance(),
            'payments' => $invoice->payments->map(fn (SubscriptionPayment $payment) => [
                'id' => $payment->id,
                'amount' => (float) $payment->amount,
                'paid_on' => $this->dates->toDisplay($payment->paid_on->toDateString()),
                'method' => $payment->method,
                'reference' => $payment->reference,
                'note' => $payment->note,
            ])->values(),
        ];
    }
}
