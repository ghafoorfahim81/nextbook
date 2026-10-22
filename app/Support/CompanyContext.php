<?php

namespace App\Support;

use App\Enums\CostingMethod;
use App\Models\Account\Account;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Posting references for the company the current request is acting in.
 *
 * These used to be written to the global cache keys `gl_accounts`, `home_currency`
 * and `costing_method` on every request, so whichever request ran last decided which
 * accounts every other request posted to. Two users in different branches were
 * enough to post a sale against the wrong receivable account.
 *
 * Everything here is resolved per company and memoised per company for the lifetime
 * of the process, so a value can never be handed to a request belonging to another
 * company. Formerly BranchContext: the chart of accounts and the currency set moved
 * up to the company in phase 2, and only branchId() still answers for the branch.
 */
final class CompanyContext
{
    /**
     * Slugs of the default accounts the posting code looks up by name.
     */
    public const GL_ACCOUNT_SLUGS = [
        'sales-revenue',
        'account-receivable',
        'account-payable',
        'product-income',
        'cash-in-hand',
        'cost-of-goods-sold',
        'inventory-stock',
        'retained-earnings',
        'opening-balance-equity',
        'non-inventory-items',
        'raw-materials',
        'finished-goods',
        'other-expenses',
        'discount-to-customer',
        'discount-from-supplier',
        // Landed costs and stock adjustments post through their own clearing
        // and variance accounts, so they have to be resolvable per branch too.
        'landed-costs-clearing',
        'freight-customs-clearing',
        'inventory-shrinkage-and-wastage',
        'inventory-adjustments',
        // Freight for moving goods between the company's own warehouses. An
        // item transfer defaults its expense line to this account.
        'item-transfer-expense',
        'contra-clearing',
        // Realised exchange differences and unapplied money, both resolved by
        // slug only — the names are localised into Dari and Pashto, so any
        // lookup by name breaks the moment someone switches language.
        'fx-gain',
        'fx-loss',
        'customer-advances',
        'supplier-advances',
        // Payroll. Staff cost is kept out of trade payables so the balance sheet
        // can separate what is owed to suppliers from what is owed to people,
        // and so withheld wage tax sits as its own remittable liability rather
        // than being buried in salary expense.
        'payroll-liabilities',
        'salary-tax-payable',
        'employee-advances',
        'employee-loans-receivable',
        'permanent-staff-salary',
        'temporary-staff-salary',
        'consultant-professional-salary',
        'allowances-commissions',
        'overtime-expense',
        'staff-benefits-expense',
        'employee-recruitment-training',
    ];

    /** @var array<string, Collection<string, string>> */
    private static array $glAccounts = [];

    /** @var array<string, Currency|null> */
    private static array $homeCurrency = [];

    /**
     * The branch the current request is acting on.
     */
    public static function branchId(): ?string
    {
        if (app()->bound('active_branch_id')) {
            $branchId = app('active_branch_id');

            if ($branchId) {
                return (string) $branchId;
            }
        }

        $branchId = Auth::user()?->branch_id;

        return $branchId ? (string) $branchId : null;
    }

    /**
     * The company the current request is acting in.
     *
     * The chart of accounts and the currency set moved up to the company, so
     * the lookups below key on this rather than on the branch. Resolved from
     * the container binding the middleware sets, never from request input.
     */
    public static function companyId(): ?string
    {
        if (app()->bound('active_company_id')) {
            $companyId = app('active_company_id');

            if ($companyId) {
                return (string) $companyId;
            }
        }

        $companyId = Auth::user()?->company_id;

        return $companyId ? (string) $companyId : null;
    }

    /**
     * The company the current request is acting in, as a model.
     *
     * Read this, never `auth()->user()->company`: since phase 5 a user can act
     * in a company other than their home one, and the home company is what the
     * relation returns. SetActiveCompany binds the model once per request; the
     * id check keeps a binding left over from an earlier request in the same
     * process (a test, a worker) from answering for a different company.
     */
    public static function company(): ?Company
    {
        $companyId = self::companyId();

        if (! $companyId) {
            return null;
        }

        if (app()->bound('active_company')) {
            $company = app('active_company');

            if ($company instanceof Company && (string) $company->getKey() === $companyId) {
                return $company;
            }
        }

        $company = Company::query()->find($companyId);

        if ($company && app()->bound('active_company_id') && (string) app('active_company_id') === $companyId) {
            app()->instance('active_company', $company);
        }

        return $company;
    }

    /**
     * Default posting accounts for a company, keyed by slug.
     *
     * @return Collection<string, string>
     */
    public static function glAccounts(?string $companyId = null): Collection
    {
        $companyId = $companyId ?? self::companyId();

        if (! $companyId) {
            return collect();
        }

        return self::$glAccounts[$companyId] ??= Account::query()
            ->withoutGlobalScope('companySpecific')
            ->where('company_id', $companyId)
            ->whereIn('slug', self::GL_ACCOUNT_SLUGS)
            ->pluck('id', 'slug');
    }

    /**
     * A single default posting account id, or null when the company does not
     * have it.
     */
    public static function glAccount(string $slug, ?string $companyId = null): ?string
    {
        return self::glAccounts($companyId)->get($slug);
    }

    /**
     * The base currency of a company.
     */
    public static function homeCurrency(?string $companyId = null): ?Currency
    {
        $companyId = $companyId ?? self::companyId();

        if (! $companyId) {
            return null;
        }

        return self::$homeCurrency[$companyId] ??= Currency::query()
            ->withoutGlobalScope('companySpecific')
            ->where('company_id', $companyId)
            ->where('is_base_currency', true)
            ->first();
    }

    /**
     * Costing method for the current company. Company-level, not branch-level.
     */
    public static function costingMethod(): string
    {
        return self::company()?->costing_method?->value
            ?? CostingMethod::FIFO->value;
    }

    /**
     * Drop memoised values so the next lookup re-reads them.
     *
     * Call after creating, renaming or deleting default accounts or currencies.
     */
    public static function flush(?string $companyId = null): void
    {
        if ($companyId === null) {
            self::$glAccounts = [];
            self::$homeCurrency = [];

            return;
        }

        unset(self::$glAccounts[$companyId], self::$homeCurrency[$companyId]);
    }
}
