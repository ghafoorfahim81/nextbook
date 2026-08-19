<?php

namespace App\Services\Accounting;

use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Support\BranchContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Moves the old sale_receives allocations into settlements.
 *
 * sale_receives already was an allocation table — one receipt legitimately
 * spans several invoices — so the rows carry real information and are migrated
 * rather than discarded. What they do not carry is rates: the amount was stored
 * in base currency and the exchange rate lived on the transaction header.
 *
 * There is therefore NO historical FX to recognise. Every backfilled row gets
 * target_rate = settlement_rate and forex_amount = 0. Inventing a gain or loss
 * from rates nobody recorded at the time would put numbers in the P&L that
 * never happened.
 *
 * Rows that cannot be matched with certainty are logged and skipped. Guessing
 * which line a payment relieved is exactly the kind of silent wrong answer this
 * whole redesign exists to remove.
 */
class SaleReceiveBackfill
{
    /** @var array<int, array<string, mixed>> */
    private array $skipped = [];

    private int $migrated = 0;

    private int $alreadyPresent = 0;

    /**
     * @return array{migrated: int, already_present: int, skipped: array<int, array<string, mixed>>}
     */
    public function run(bool $dryRun = false): array
    {
        $this->skipped = [];
        $this->migrated = 0;
        $this->alreadyPresent = 0;

        if (! DB::getSchemaBuilder()->hasTable('sale_receives')) {
            return $this->result();
        }

        DB::transaction(function () use ($dryRun) {
            DB::table('sale_receives')
                ->whereNull('deleted_at')
                ->orderBy('id')
                ->chunkById(500, function ($rows) use ($dryRun) {
                    foreach ($rows as $row) {
                        $this->migrateRow($row, $dryRun);
                    }
                }, 'id');
        });

        if ($this->skipped !== []) {
            Log::warning('sale_receives backfill skipped rows.', [
                'count' => count($this->skipped),
                'rows' => $this->skipped,
            ]);
        }

        return $this->result();
    }

    private function migrateRow(object $row, bool $dryRun): void
    {
        $sale = DB::table('sales')->where('id', $row->sale_id)->first(['id', 'number', 'customer_id', 'branch_id']);
        $receipt = DB::table('receipts')->where('id', $row->receipt_id)->first(['id', 'number', 'ledger_id', 'branch_id']);

        if (! $sale || ! $receipt) {
            $this->skip($row, 'sale or receipt is missing');

            return;
        }

        $branchId = (string) ($row->branch_id ?? $sale->branch_id);
        $arAccountId = BranchContext::glAccount('account-receivable', $branchId);

        if (! $arAccountId) {
            $this->skip($row, 'branch has no account-receivable account');

            return;
        }

        $targetLine = $this->partyLine(Sale::class, (string) $sale->id, $arAccountId, 'debit');
        $settlingLine = $this->partyLine(Receipt::class, (string) $receipt->id, $arAccountId, 'credit');

        if (! $targetLine) {
            $this->skip($row, 'the sale has no receivable line to relieve');

            return;
        }

        if (! $settlingLine) {
            $this->skip($row, 'the receipt has no receivable line');

            return;
        }

        $exists = DB::table('settlements')
            ->where('settling_line_id', $settlingLine->id)
            ->where('target_line_id', $targetLine->id)
            ->exists();

        if ($exists) {
            // Re-running must be safe. The unique pair index is the backstop;
            // this check is what makes a second run quiet rather than fatal.
            $this->alreadyPresent++;

            return;
        }

        $targetRate = Decimal::rate($targetLine->rate);

        // sale_receives.amount was stored in BASE currency — the old code
        // compared it against `debit x rate`. settlements.amount_applied is in
        // the TARGET's currency, so it converts back through the booking rate.
        $storedBase = Decimal::amount($row->amount);
        $amountApplied = Decimal::isZero($targetRate, Decimal::RATE_SCALE)
            ? $storedBase
            : Decimal::amount(bcdiv($storedBase, $targetRate, Decimal::AMOUNT_SCALE + 2));

        if (! Decimal::isPositive($amountApplied)) {
            $this->skip($row, 'allocation is zero once converted to document currency');

            return;
        }

        $alreadyApplied = Decimal::amount(
            DB::table('settlements')
                ->where('target_line_id', $targetLine->id)
                ->whereNull('deleted_at')
                ->sum('amount_applied')
        );

        $remaining = Decimal::sub(Decimal::amount($targetLine->debit), $alreadyApplied);

        if (Decimal::cmp($amountApplied, $remaining) > 0) {
            $this->skip($row, 'allocation exceeds what is open on the sale', [
                'amount_applied' => $amountApplied,
                'remaining' => $remaining,
            ]);

            return;
        }

        $this->migrated++;

        if ($dryRun) {
            return;
        }

        DB::table('settlements')->insert([
            'id' => (string) Str::ulid(),
            'transaction_id' => $settlingLine->transaction_id,
            'settling_line_id' => $settlingLine->id,
            'target_line_id' => $targetLine->id,
            'ledger_id' => $sale->customer_id,
            'currency_id' => $targetLine->currency_id,
            'amount_applied' => $amountApplied,
            'target_rate' => $targetRate,
            // No historical FX exists, so the cash is treated as having moved
            // at the rate the claim was booked at.
            'settlement_rate' => $targetRate,
            'base_relieved' => Decimal::toBase($amountApplied, $targetRate),
            'forex_amount' => 0,
            'is_cross_currency' => false,
            'branch_id' => $branchId,
            // Preserved, not stamped with today and whoever ran the migration.
            'created_by' => $row->created_by,
            'updated_by' => $row->updated_by,
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ]);
    }

    /**
     * The AR line of a document's own transaction.
     */
    private function partyLine(string $referenceType, string $referenceId, string $accountId, string $column): ?object
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', $referenceType)
            ->where('t.reference_id', $referenceId)
            ->whereNull('t.deleted_at')
            ->whereNull('tl.deleted_at')
            ->where('tl.account_id', $accountId)
            ->where("tl.{$column}", '>', 0)
            ->orderBy('tl.created_at')
            ->first(['tl.id', 'tl.transaction_id', 'tl.currency_id', 'tl.rate', 'tl.debit', 'tl.credit']);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function skip(object $row, string $reason, array $extra = []): void
    {
        $this->skipped[] = array_merge([
            'sale_receive_id' => $row->id,
            'sale_id' => $row->sale_id,
            'receipt_id' => $row->receipt_id,
            'amount' => $row->amount,
            'reason' => $reason,
        ], $extra);
    }

    /**
     * @return array{migrated: int, already_present: int, skipped: array<int, array<string, mixed>>}
     */
    private function result(): array
    {
        return [
            'migrated' => $this->migrated,
            'already_present' => $this->alreadyPresent,
            'skipped' => $this->skipped,
        ];
    }
}
