<?php

namespace App\Services\Accounting;

use App\Enums\PaymentStatus;
use App\Support\BranchContext;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Keeps the paid/partially-paid/unpaid badge on sales and purchases in step
 * with what has actually been settled.
 *
 * This replaces BillAllocationService. That class both wrote allocations and
 * derived status from them; allocations now live in settlements, so all that is
 * left here is the derivation. There is deliberately only ONE place that can
 * answer "how much is left on this document" — the settlements table — because
 * two places would disagree, and a sale marked Paid whose receivable is still
 * open is the kind of disagreement nobody notices until year end.
 *
 * The document's own receivable/payable LINE is the source of the bill amount,
 * not a recomputation from item rows. The line is what the general ledger
 * carries, and the badge should say the same thing the ledger does.
 */
class PaymentStatusService
{
    public function __construct(
        private readonly SettlementService $settlements,
    ) {
    }

    /**
     * @param  array<int, string>  $saleIds
     */
    public function recalculateSales(array $saleIds): void
    {
        $this->recalculate(
            documentClass: \App\Models\Sale\Sale::class,
            table: 'sales',
            ids: $saleIds,
            accountSlug: 'account-receivable',
            claimColumn: 'debit',
        );
    }

    /**
     * @param  array<int, string>  $purchaseIds
     */
    public function recalculatePurchases(array $purchaseIds): void
    {
        $this->recalculate(
            documentClass: \App\Models\Purchase\Purchase::class,
            table: 'purchases',
            ids: $purchaseIds,
            accountSlug: 'account-payable',
            claimColumn: 'credit',
        );
    }

    /**
     * Open receivables for a customer, ready for a receipt form.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function openItemsForLedger(string $ledgerId, ?string $currencyId = null): Collection
    {
        return $this->settlements->openItems($ledgerId, $currencyId);
    }

    /**
     * @param  array<int, string>  $ids
     */
    private function recalculate(
        string $documentClass,
        string $table,
        array $ids,
        string $accountSlug,
        string $claimColumn
    ): void {
        $ids = array_values(array_filter(array_unique($ids)));

        if ($ids === []) {
            return;
        }

        $accountIds = DB::table('accounts')
            ->where('slug', $accountSlug)
            ->whereNull('deleted_at')
            ->pluck('id');

        $settled = "COALESCE((SELECT SUM(s.amount_applied) FROM settlements s"
            . " WHERE s.target_line_id = tl.id AND s.deleted_at IS NULL), 0)";

        $rows = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', $documentClass)
            ->whereIn('t.reference_id', $ids)
            ->whereNull('t.deleted_at')
            ->whereNull('tl.deleted_at')
            ->whereIn('tl.account_id', $accountIds)
            ->where("tl.{$claimColumn}", '>', 0)
            ->selectRaw("t.reference_id, SUM(tl.{$claimColumn}) as claimed, SUM({$settled}) as settled")
            ->groupBy('t.reference_id')
            ->get()
            ->keyBy('reference_id');

        foreach ($ids as $id) {
            $row = $rows->get($id);

            // No receivable line at all means the document was settled at the
            // point of sale — a cash sale is paid, not unpaid.
            if (! $row) {
                DB::table($table)->where('id', $id)->update([
                    'payment_status' => PaymentStatus::Paid->value,
                ]);

                continue;
            }

            $applied = Decimal::amount($row->settled);
            $remaining = Decimal::sub(Decimal::amount($row->claimed), $applied);

            DB::table($table)->where('id', $id)->update([
                'payment_status' => $this->status($applied, $remaining)->value,
            ]);
        }
    }

    private function status(string $applied, string $remaining): PaymentStatus
    {
        if (Decimal::cmp($remaining, '0') <= 0) {
            return PaymentStatus::Paid;
        }

        if (Decimal::isPositive($applied)) {
            return PaymentStatus::PartiallyPaid;
        }

        return PaymentStatus::Unpaid;
    }

    /**
     * Documents touched by a voucher, so their badges can be refreshed after it
     * is posted, edited or deleted.
     *
     * @return array{sales: array<int, string>, purchases: array<int, string>}
     */
    public function documentsSettledBy(string $transactionId): array
    {
        $rows = DB::table('settlements as s')
            ->join('transaction_lines as tl', 'tl.id', '=', 's.target_line_id')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('s.transaction_id', $transactionId)
            ->select('t.reference_type', 't.reference_id')
            ->distinct()
            ->get();

        return [
            'sales' => $rows->where('reference_type', \App\Models\Sale\Sale::class)
                ->pluck('reference_id')->filter()->map(fn ($id) => (string) $id)->values()->all(),
            'purchases' => $rows->where('reference_type', \App\Models\Purchase\Purchase::class)
                ->pluck('reference_id')->filter()->map(fn ($id) => (string) $id)->values()->all(),
        ];
    }

    /**
     * Every application against a ledger's claims, newest first.
     *
     * Grouped by the claim it relieved, so the customer view can show a
     * document alongside the receipts that paid it and the exchange difference
     * each one realised.
     *
     * @return array<string, array<int, array<string, mixed>>>  keyed by target_line_id
     */
    public function settlementHistoryForLedger(string $ledgerId): array
    {
        return DB::table('settlements as s')
            ->join('transactions as t', 't.id', '=', 's.transaction_id')
            ->leftJoin('currencies as c', 'c.id', '=', 's.currency_id')
            ->where('s.ledger_id', $ledgerId)
            ->whereNull('s.deleted_at')
            ->whereNull('t.deleted_at')
            ->orderByDesc('t.date')
            ->orderByDesc('s.created_at')
            ->get([
                's.id',
                's.target_line_id',
                's.amount_applied',
                's.target_rate',
                's.settlement_rate',
                's.base_relieved',
                's.forex_amount',
                's.is_cross_currency',
                't.date',
                't.voucher_number',
                't.reference_type',
                'c.code as currency_code',
            ])
            ->groupBy('target_line_id')
            ->map(fn ($rows) => $rows->map(fn ($row) => [
                'id' => (string) $row->id,
                'date' => $row->date,
                'voucher_number' => $row->voucher_number,
                'document_type' => $row->reference_type ? class_basename($row->reference_type) : 'Journal',
                'currency_code' => $row->currency_code,
                'amount_applied' => (float) $row->amount_applied,
                'target_rate' => (float) $row->target_rate,
                'settlement_rate' => (float) $row->settlement_rate,
                'base_relieved' => (float) $row->base_relieved,
                'forex_amount' => (float) $row->forex_amount,
                // Positive is a gain on both sides — the service normalises the
                // sign, so the UI never has to know which side it is looking at.
                'forex_kind' => $row->forex_amount < 0 ? 'loss' : ((float) $row->forex_amount > 0 ? 'gain' : 'none'),
                'is_cross_currency' => (bool) $row->is_cross_currency,
            ])->values()->all())
            ->all();
    }

    /**
     * Balance per currency for a ledger, plus the AFN total.
     *
     * Summing foreign balances into one number is the thing this must not do:
     * $500 and 500 AFN are not 1,000 of anything. Each currency is reported in
     * its own terms, and the AFN column is the base value of all of them.
     *
     * @return array{currencies: array<int, array<string, mixed>>, base_total: string}
     */
    public function balancesForLedger(string $ledgerId, ?string $branchId = null): array
    {
        $branchId = $branchId ?? BranchContext::branchId();

        $rows = DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->leftJoin('currencies as c', 'c.id', '=', 'tl.currency_id')
            ->where('tl.ledger_id', $ledgerId)
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->where('t.status', 'posted')
            ->when($branchId, fn ($query) => $query->where('t.branch_id', $branchId))
            ->groupBy('tl.currency_id', 'c.code', 'c.symbol')
            ->selectRaw(implode(', ', [
                'tl.currency_id',
                'c.code as currency_code',
                'c.symbol as currency_symbol',
                'SUM(tl.debit) as debit',
                'SUM(tl.credit) as credit',
                'SUM(tl.base_debit) as base_debit',
                'SUM(tl.base_credit) as base_credit',
            ]))
            ->get();

        $baseTotal = '0';
        $currencies = [];

        foreach ($rows as $row) {
            $balance = Decimal::sub(Decimal::amount($row->debit), Decimal::amount($row->credit));
            $baseBalance = Decimal::sub(Decimal::amount($row->base_debit), Decimal::amount($row->base_credit));
            $baseTotal = Decimal::add($baseTotal, $baseBalance);

            $currencies[] = [
                'currency_id' => (string) $row->currency_id,
                'currency_code' => $row->currency_code,
                'currency_symbol' => $row->currency_symbol,
                'balance' => $balance,
                'base_balance' => $baseBalance,
            ];
        }

        return ['currencies' => $currencies, 'base_total' => $baseTotal];
    }
}
