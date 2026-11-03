<?php

namespace App\Console\Commands;

use App\Models\Administration\Company;
use App\Models\Platform\CompanyLicense;
use App\Services\Control\LicenseClient;
use App\Support\CompanyDatabaseContext;
use Illuminate\Console\Command;

/** Reports what each connected company uses (counters only) to the control center. Scheduled daily. */
class LicenseUsage extends Command
{
    protected $signature = 'license:usage {--company= : Only this company id}';

    protected $description = 'Report each connected company usage to the control center';

    public function handle(LicenseClient $client): int
    {
        $failed = 0;

        Company::query()->withoutGlobalScopes()->whereNull('deleted_at')
            ->when($this->option('company'), fn ($query, $id) => $query->whereKey($id))
            ->get(['id', 'name_en'])
            ->each(function (Company $company) use ($client, &$failed) {
                $connected = CompanyDatabaseContext::runFor($company->id, fn () => CompanyLicense::query()
                    ->where('company_id', $company->id)->whereNotNull('api_token')->exists());
                if (! $connected) {
                    return;
                }

                $result = $client->reportUsage($company->id);
                $failed += $result['ok'] ? 0 : 1;
                $this->line(($result['ok'] ? '  ok   ' : '  FAIL ')."{$company->name_en}: {$result['message']}");
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
