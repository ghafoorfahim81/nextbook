<?php

namespace App\Console\Commands;

use App\Models\Administration\Company;
use App\Models\Platform\CompanyLicense;
use App\Support\CompanyDatabaseContext;
use Illuminate\Console\Command;

/**
 * Ties a company to its customer record in the control center, by the token
 * the control center issued for it. After this, license:pull fetches its
 * licence. The token is stored encrypted and never shown again.
 *
 *   php artisan license:connect 01M6DTM02SCYWQ21QAJD5NYKHF <token>
 */
class LicenseConnect extends Command
{
    protected $signature = 'license:connect {company : The company id} {token : The token issued by the control center}';

    protected $description = 'Connect a company to the control center with the token issued for it';

    public function handle(): int
    {
        $company = Company::query()->withoutGlobalScopes()->find($this->argument('company'));
        if (! $company) {
            $this->error('No company with that id.');

            return self::FAILURE;
        }

        CompanyDatabaseContext::runFor($company->id, function () use ($company) {
            $row = CompanyLicense::query()->firstOrNew(['company_id' => $company->id]);
            // A new token starts the relationship over: whatever licence was
            // held belonged to the old one.
            $row->forceFill(['api_token' => $this->argument('token'), 'last_error' => null])->save();
        });

        $this->info("{$company->name_en} is connected. Run: php artisan license:pull --company={$company->id}");

        return self::SUCCESS;
    }
}
