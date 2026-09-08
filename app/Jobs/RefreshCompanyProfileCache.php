<?php

namespace App\Jobs;

use App\Models\User;
use App\Support\Inertia\CacheForget;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Drop the per-user Inertia caches that derive from the company's trade.
 *
 * `business_profile` and `preferences` are cached per user (see
 * App\Support\Inertia\CoreShared). When the company's business_type — or the
 * base currency / calendar — changes, every user in that company is still served
 * the old profile until their cache expires. Every Preferences write already
 * busts these keys; the company update did not, which is why a Computer shop
 * kept seeing batch/expiry columns after switching away from Pharmacy.
 */
class RefreshCompanyProfileCache implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public string $companyId)
    {
    }

    public function handle(): void
    {
        User::query()
            ->withTrashed()
            ->where('company_id', $this->companyId)
            ->pluck('id')
            ->each(function (string $userId): void {
                CacheForget::userById($userId, 'business_profile');
                CacheForget::userById($userId, 'preferences');
            });
    }
}
