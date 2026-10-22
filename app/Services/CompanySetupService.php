<?php

namespace App\Services;

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\User;
use App\Support\CompanyContext;
use App\Support\CompanyDatabaseContext;
use Illuminate\Support\Facades\DB;

/**
 * Bring a new company into being, ready to trade (phase 5).
 *
 * The one path for both ways a company is born — a customer signing up, and a
 * super-admin adding another company — so both end in the same state:
 *
 *  - the company row;
 *  - its main branch, provisioned (chart of accounts, currencies, units, the
 *    cash customer, the branch's warehouse — BranchProvisioningService runs
 *    the company sets first);
 *  - the chosen base currency, from the default set provisioning creates;
 *  - the creator able to act in it: it becomes their home when they have
 *    none (sign-up), and is granted to them otherwise.
 *
 * Before this, sign-up created the company row alone. Nothing provisioned it,
 * no branch was made, and the base currency was picked by id from whatever
 * currencies the form was shown — none, for a user with no company yet — so
 * the form could not be submitted at all.
 */
class CompanySetupService
{
    public function __construct(
        private readonly BranchProvisioningService $provisioning,
        private readonly UserAccessService $access,
    ) {
    }

    /**
     * The base currencies a new company may start with: any currency the
     * catalog knows (the list the sign-up form offers as currencyDefinitions).
     *
     * @return array<int, string>
     */
    public static function baseCurrencyCodes(): array
    {
        return array_keys(Currency::currencyList());
    }

    /**
     * What the set-up screen reports, step by step, in the order the work is
     * done — each step's provisioning modules summed. The screen lists the same
     * keys; the counts are what was actually written.
     */
    public const SUMMARY_STEPS = [
        'company' => [],
        'branch' => [],
        'accounts' => ['account_types', 'accounts'],
        'currencies' => ['currencies'],
        'units' => ['quantities', 'unit_measures', 'sizes'],
        'catalog' => ['categories', 'brands'],
        'cash_customer' => ['ledgers'],
        'expenses' => ['expense_categories', 'journal_classes'],
        'hr' => ['departments', 'shifts', 'leave_types', 'salary_components', 'tax_brackets'],
        'financial_period' => ['financial_periods'],
        'warehouse' => ['warehouses'],
        'access' => [],
    ];

    /**
     * @param  array<string, mixed>  $attributes  validated company attributes
     * @return array{company: Company, summary: array<string, int>}
     */
    public function create(array $attributes, string $baseCurrencyCode, User $creator, ?string $branchName = null): array
    {
        return DB::transaction(function () use ($attributes, $baseCurrencyCode, $creator, $branchName): array {
            $company = Company::create($attributes + [
                'created_by' => $creator->id,
                'updated_by' => $creator->id,
            ]);

            // The new company's own rows have to be written by a connection
            // acting for it: the policies take nothing else.
            [$branch, $counts] = CompanyDatabaseContext::runFor($company->id, function () use ($company, $creator, $branchName, $baseCurrencyCode): array {
                $branch = Branch::create([
                    'company_id' => $company->id,
                    'name' => $branchName ?: __('general.main_branch'),
                    'is_main' => true,
                    'created_by' => $creator->id,
                ]);

                $counts = $this->provisioning->provision($branch, $creator->id);

                if ($this->setBaseCurrency($company, $baseCurrencyCode)) {
                    $counts['currencies'] = ($counts['currencies'] ?? 0) + 1;
                }

                return [$branch, $counts];
            });

            if (! $creator->company_id) {
                // Sign-up: this is the user's first company, so it is home.
                $creator->forceFill(['company_id' => $company->id, 'branch_id' => $branch->id])->save();
            } else {
                $this->access->grantCompany($creator, $company->id, $creator->id);
            }

            CompanyContext::flush($company->id);

            return ['company' => $company, 'summary' => $this->summarise($counts)];
        });
    }

    /**
     * @param  array<string, int>  $counts  provisioning's rows per module
     * @return array<string, int>
     */
    private function summarise(array $counts): array
    {
        $summary = [];

        foreach (self::SUMMARY_STEPS as $step => $modules) {
            $summary[$step] = $modules === []
                ? 1
                : array_sum(array_map(fn (string $module) => (int) ($counts[$module] ?? 0), $modules));
        }

        return $summary;
    }
    /**
     * Exactly one base currency, at rate 1. The other defaults keep the rate
     * provisioning gave them (zero: not yet known) for the company to fill in.
     *
     * Provisioning creates a handful of currencies; a base chosen from the
     * wider catalog is added from its definition there.
     */
    private function setBaseCurrency(Company $company, string $code): bool
    {
        $added = false;

        $currencies = Currency::query()
            ->withoutGlobalScopes()
            ->where('company_id', $company->id);

        if (! (clone $currencies)->where('code', $code)->exists()) {
            $definition = Currency::currencyList()[$code];

            Currency::query()->create([
                'company_id' => $company->id,
                'code' => $code,
                'name' => $definition['name'],
                'local_name' => $definition['local_name'] ?? null,
                'symbol' => $definition['symbol'],
                'format' => $definition['format'],
                'flag' => $definition['flag'] ?? null,
                'exchange_rate' => 1,
                'is_active' => true,
                'is_base_currency' => false,
            ]);

            $added = true;
        }

        (clone $currencies)->where('code', '!=', $code)->update(['is_base_currency' => false]);
        (clone $currencies)->where('code', $code)->update(['is_base_currency' => true, 'exchange_rate' => 1]);

        return $added;
    }
}
