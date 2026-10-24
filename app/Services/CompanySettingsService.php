<?php

namespace App\Services;

use App\Models\Administration\Company;
use App\Support\Inertia\CacheKey;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;

/**
 * Saves the settings a company shares (the company_keys of
 * config/preferences.php): the company edit page and the settings route both
 * end here, so the activity log entry and the cache drop are the same.
 */
class CompanySettingsService
{
    /**
     * @param  array<string, mixed>  $validated  already validated against UpdatePreferencesRequest::companyRules()
     */
    public function save(Company $company, array $validated): void
    {
        $before = Settings::forCompany($company);
        Settings::putForCompany($company, $validated);
        $after = Settings::forCompany($company->fresh());

        if ($after != $before) {
            app(ActivityLogService::class)->logUpdate(
                reference: $company,
                before: $before,
                after: $after,
                module: 'setting',
                description: "Company settings updated for {$company->localizedName('en')}.",
                metadata: [
                    'action' => 'company_settings_update',
                ],
            );
        }

        Cache::forget(CacheKey::forCompany((string) $company->id, 'business_profile'));
    }
}
