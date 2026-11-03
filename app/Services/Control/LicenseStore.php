<?php

namespace App\Services\Control;

use App\Models\Platform\CompanyLicense;
use App\Models\Platform\Package;
use App\Models\Platform\Subscription;
use Illuminate\Support\Facades\Log;

/**
 * Turns the licence a company holds into what the rest of the application
 * already understands: a Subscription with a Package. Nothing downstream (the
 * read-only middleware, the module switches, the user and branch limits, the
 * banner) needs to know where the answer came from.
 *
 * What the control center says is taken as signed, not trusted: the signature
 * is checked again here, and a licence that does not verify is ignored.
 *
 * The state of a subscription is worked out here from the dates and the block,
 * never read from the licence, because a licence does not change on the day a
 * subscription ends. When the control center has not been reachable for a
 * while the end date is stretched by the grace days the licence came with, so
 * an outage on our side never stops a customer at once, and cutting the
 * network never buys more than those days.
 */
class LicenseStore
{
    /**
     * The company's licence as a subscription, or null when it has none (it is
     * not connected, or what it holds does not verify).
     *
     * Reads through row level security, so it answers for the company the
     * request is acting for and no other.
     */
    public function effective(string $companyId): ?Subscription
    {
        $row = CompanyLicense::query()->where('company_id', $companyId)->first();

        if (! $row || ! $row->payload || ! $row->signature) {
            return null;
        }

        if (! LicenseVerifier::verify($row->payload, $row->signature, config('control.public_key'))) {
            Log::warning('A stored licence did not verify and was ignored.', ['company_id' => $companyId]);

            return null;
        }

        return $this->toSubscription($row);
    }

    public function isStale(CompanyLicense $row): bool
    {
        return $row->fetched_at === null
            || $row->fetched_at->lt(now()->subHours((int) config('control.stale_after_hours')));
    }

    private function toSubscription(CompanyLicense $row): Subscription
    {
        $payload = $row->payload;

        $endsOn = \Illuminate\Support\Carbon::parse($payload['ends_on']);
        if ($this->isStale($row)) {
            $endsOn = $endsOn->addDays($row->grace_days);
        }

        $subscription = new Subscription([
            'company_id' => $row->company_id,
            'starts_on' => $payload['starts_on'],
            'ends_on' => $endsOn->toDateString(),
            'block_reason' => $payload['block_reason'] ?? null,
        ]);
        if (! empty($payload['blocked'])) {
            $subscription->blocked_at = $row->fetched_at ?? now();
        }

        $package = new Package();
        $package->forceFill([
            'code' => 'licence',
            'name' => $payload['package'] ?? 'NextBook',
            'modules' => array_values((array) ($payload['modules'] ?? [])),
            'max_users' => $payload['max_users'] ?? null,
            'max_branches' => $payload['max_branches'] ?? null,
            'period_months' => 12,
        ]);
        $subscription->setRelation('package', $package);

        return $subscription;
    }
}
