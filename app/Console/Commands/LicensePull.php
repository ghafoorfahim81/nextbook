<?php

namespace App\Console\Commands;

use App\Models\Administration\Company;
use App\Models\Platform\CompanyLicense;
use App\Services\Control\LicenseClient;
use App\Support\CompanyDatabaseContext;
use Illuminate\Console\Command;

/**
 * Fetches the licence of every connected company (or of one), and verifies it.
 * Scheduled every few hours. A company that cannot be reached keeps the
 * licence it has.
 */
class LicensePull extends Command
{
    protected $signature = 'license:pull {--company= : Only this company id}';

    protected $description = 'Fetch the licences of connected companies from the control center';

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

                $result = $client->pull($company->id);
                $failed += $result['ok'] ? 0 : 1;
                $this->line(($result['ok'] ? '  ok   ' : '  FAIL ')."{$company->name_en}: {$result['message']}");

                // The invoices are for display only, so they are fetched once
                // the licence itself is in hand, and a failure here is shown but
                // never makes the licence pull look as if it failed.
                if ($result['ok']) {
                    $invoices = $client->pullInvoices($company->id);
                    $this->line(($invoices['ok'] ? '  ok   ' : '  warn ')."{$company->name_en}: {$invoices['message']}");
                }
            });

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
