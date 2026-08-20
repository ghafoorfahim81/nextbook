<?php

namespace App\Support;

use App\Enums\CostingMethod;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Posting references for the branch the current request is acting on.
 *
 * These used to be written to the global cache keys `gl_accounts`, `home_currency`
 * and `costing_method` on every request, so whichever request ran last decided which
 * branch's accounts every other request posted to. Two users in different branches
 * were enough to post a sale against the wrong branch's receivable account.
 *
 * Everything here is resolved per branch and memoised per branch for the lifetime of
 * the process, so a value can never be handed to a request belonging to another branch.
 */
final class BranchContext
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
     * Default posting accounts for a branch, keyed by slug.
     *
     * @return Collection<string, string>
     */
    public static function glAccounts(?string $branchId = null): Collection
    {
        $branchId = $branchId ?? self::branchId();

        if (! $branchId) {
            return collect();
        }

        return self::$glAccounts[$branchId] ??= Account::query()
            ->withoutGlobalScope('branchSpecific')
            ->where('branch_id', $branchId)
            ->whereIn('slug', self::GL_ACCOUNT_SLUGS)
            ->pluck('id', 'slug');
    }

    /**
     * A single default posting account id, or null when the branch does not have it.
     */
    public static function glAccount(string $slug, ?string $branchId = null): ?string
    {
        return self::glAccounts($branchId)->get($slug);
    }

    /**
     * The base currency of a branch.
     */
    public static function homeCurrency(?string $branchId = null): ?Currency
    {
        $branchId = $branchId ?? self::branchId();

        if (! $branchId) {
            return null;
        }

        return self::$homeCurrency[$branchId] ??= Currency::query()
            ->withoutGlobalScope('branchSpecific')
            ->where('branch_id', $branchId)
            ->where('is_base_currency', true)
            ->first();
    }

    /**
     * Costing method for the current company. Company-level, not branch-level.
     */
    public static function costingMethod(): string
    {
        return Auth::user()?->company?->costing_method?->value
            ?? CostingMethod::FIFO->value;
    }

    /**
     * Drop memoised values so the next lookup re-reads them.
     *
     * Call after creating, renaming or deleting default accounts or currencies.
     */
    public static function flush(?string $branchId = null): void
    {
        if ($branchId === null) {
            self::$glAccounts = [];
            self::$homeCurrency = [];

            return;
        }

        unset(self::$glAccounts[$branchId], self::$homeCurrency[$branchId]);
    }
}
