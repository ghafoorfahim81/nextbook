<?php

namespace App\Http\Controllers\Concerns;

use App\Enums\PaymentMode;
use App\Models\Account\Account;
use Illuminate\Database\Eloquent\Builder;

/**
 * The parts of the receipt and payment list pages that are identical on both
 * sides of the cash: their filter option lists, and sorting by things that are
 * not columns on the voucher.
 */
trait ListsCashMovements
{
    /**
     * Cash-or-bank accounts, named in the user's own locale.
     *
     * Selecting only `name` left the Persian UI showing English account names
     * in a filter whose rows were labelled in Persian.
     */
    protected function cashBankAccountOptions()
    {
        $locale = app()->getLocale();

        return Account::whereHas('accountType', fn ($q) => $q->where('slug', 'cash-or-bank'))
            ->orderBy('name')
            ->get(['id', 'name', 'local_name'])
            ->map(fn (Account $account) => [
                'id' => $account->id,
                'name' => $locale === 'en'
                    ? $account->name
                    : ($account->local_name ?: $account->name),
            ])
            ->values();
    }

    /**
     * @return array<int, array{id: string, name: string}>
     */
    protected function paymentModeOptions(): array
    {
        return collect(PaymentMode::cases())
            ->map(fn (PaymentMode $mode) => ['id' => $mode->value, 'name' => $mode->getLabel()])
            ->values()
            ->all();
    }

    /**
     * Order a receipt/payment list by a column the table shows but the voucher
     * does not store.
     *
     * The ledger, the currency and the cash account all live one or two hops
     * away — the last of them behind the voucher's cash line — so each is a
     * correlated subquery rather than a join. Joining would multiply rows
     * (a voucher has several lines) and break the paginator's count.
     *
     * Anything not listed here falls through to a plain column sort, which is
     * what number, date and payment_mode need.
     */
    protected function applyCashMovementSort(Builder $query, string $sortField, string $sortDirection, string $referenceClass): Builder
    {
        $direction = strtolower($sortDirection) === 'asc' ? 'asc' : 'desc';
        $table = $query->getModel()->getTable();
        $locale = app()->getLocale();
        $accountName = $locale === 'en'
            ? 'a.name'
            : "COALESCE(NULLIF(a.local_name, ''), a.name)";

        return match ($sortField) {
            'ledger_name' => $query->orderByRaw(
                "(SELECT l.name FROM ledgers l WHERE l.id = {$table}.ledger_id) {$direction}"
            ),

            'currency_code' => $query->orderByRaw(
                "(SELECT c.code FROM transactions t
                    JOIN currencies c ON c.id = t.currency_id
                   WHERE t.reference_id = {$table}.id
                     AND t.reference_type = ?
                     AND t.deleted_at IS NULL
                   LIMIT 1) {$direction}",
                [$referenceClass]
            ),

            // Matched on the account TYPE, exactly as Receipt::cashLine() does —
            // a settlement voucher also carries the receivable/payable relief
            // and any exchange difference, and line order is not guaranteed.
            'bank_account_name' => $query->orderByRaw(
                "(SELECT {$accountName} FROM transactions t
                    JOIN transaction_lines tl ON tl.transaction_id = t.id AND tl.deleted_at IS NULL
                    JOIN accounts a ON a.id = tl.account_id
                    JOIN account_types att ON att.id = a.account_type_id
                   WHERE t.reference_id = {$table}.id
                     AND t.reference_type = ?
                     AND t.deleted_at IS NULL
                     AND att.slug = 'cash-or-bank'
                   LIMIT 1) {$direction}",
                [$referenceClass]
            ),

            // Neither table has an amount column any more — the money moved to
            // the voucher's cash line — so the sortable Amount header was
            // ordering by a column Postgres rejects outright. GREATEST covers
            // both sides: a receipt debits the cash line, a payment credits it.
            'amount' => $query->orderByRaw(
                "(SELECT GREATEST(tl.debit, tl.credit) FROM transactions t
                    JOIN transaction_lines tl ON tl.transaction_id = t.id AND tl.deleted_at IS NULL
                    JOIN accounts a ON a.id = tl.account_id
                    JOIN account_types att ON att.id = a.account_type_id
                   WHERE t.reference_id = {$table}.id
                     AND t.reference_type = ?
                     AND t.deleted_at IS NULL
                     AND att.slug = 'cash-or-bank'
                   LIMIT 1) {$direction}",
                [$referenceClass]
            ),

            // The table shows the enum's translated label, but sorting on the
            // stored value keeps the SQL simple and the two modes apart, which
            // is all the grouping is for.
            'payment_mode_label' => $query->orderBy('payment_mode', $direction),

            default => $query->orderBy($sortField, $direction),
        };
    }
}
