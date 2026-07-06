<?php

namespace App\Services;

use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
use App\Exceptions\InvalidStatusTransitionException;
use App\Models\Inventory\StockMovement;
use App\Models\Transaction\Transaction;
use App\Support\TransactionStateMachine;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class TransactionService
{
    private $dateConversionService;

    public function __construct(DateConversionService $dateConversionService, private StockService $stockService)
    {
        $this->dateConversionService = $dateConversionService;
    }

    /**
     * Core posting method
     *
     * ONE transaction (voucher)
     * MANY transaction lines
     * debit MUST equal credit
     */
    public function post(array $header, array $lines): Transaction
    {
        return DB::transaction(function () use ($header, $lines) {

            // -----------------------------
            // 1️⃣ Validate header
            // -----------------------------
            $this->validateHeader($header);
            $this->validateLines($lines);
            $status = TransactionStatus::tryFrom($header['status'] ?? TransactionStatus::POSTED->value)
                ?? TransactionStatus::POSTED;

            // -----------------------------
            // 2️⃣ Create transaction (header)
            // -----------------------------
            $transaction = Transaction::create([
                'currency_id'    => $header['currency_id'],
                'rate'           => $header['rate'],
                'date'           => ($header['date']),
                'voucher_number' => $header['voucher_number'] ?? null,
                'reference_type' => $header['reference_type'] ?? null,
                'reference_id'   => $header['reference_id'] ?? null,
                'remark'         => $header['remark'] ?? null,
                'status'         => $status->value,
                'posted_at'      => $status === TransactionStatus::POSTED ? now() : null,
                'posted_by'      => $status === TransactionStatus::POSTED ? Auth::id() : null,
                'posting_payload' => $status === TransactionStatus::DRAFT
                    ? array_merge($header['posting_payload'] ?? [], ['lines' => $lines])
                    : ($header['posting_payload'] ?? null),
                'created_by'     => Auth::id(),
            ]);

            if ($status === TransactionStatus::DRAFT) {
                return $transaction;
            }

            // -----------------------------
            // 3️⃣ Insert lines + enforce balance
            // -----------------------------
            $this->createLines($transaction, $lines);

            return $transaction;
        });
    }

    public function postDraft(Transaction $draft, array $lines = []): Transaction
    {
        return DB::transaction(function () use ($draft, $lines) {
            $from = TransactionStatus::tryFrom((string) $draft->status);

            if ($from !== TransactionStatus::DRAFT) {
                throw InvalidStatusTransitionException::for($from ?? TransactionStatus::POSTED, TransactionStatus::POSTED);
            }

            if (!TransactionStateMachine::canTransition($from, TransactionStatus::POSTED)) {
                throw InvalidStatusTransitionException::for($from, TransactionStatus::POSTED);
            }

            $lines = $lines ?: (array) data_get($draft->posting_payload, 'lines', []);
            $this->validateLines($lines);
            $this->createLines($draft, $lines);

            $draft->update([
                'status' => TransactionStatus::POSTED->value,
                'posted_at' => now(),
                'posted_by' => Auth::id(),
                'updated_by' => Auth::id(),
            ]);

            return $draft->refresh();
        });
    }

    // ======================================================
    // 🔒 VALIDATION
    // ======================================================

    protected function validateHeader(array $header): void
    {
        validator($header, [
            'currency_id'    => 'required|exists:currencies,id',
            'rate'           => 'required|numeric|min:0',
            'date'           => 'required|date',
            'voucher_number' => 'nullable',
            'reference_type' => 'nullable|string',
            'reference_id'   => 'nullable|string',
            'remark'         => 'nullable|string',
            'status'         => 'nullable|string',
        ])->validate();
    }

    protected function validateLines(array $lines): void
    {
        if (empty($lines)) {
            throw new Exception('Transaction must have at least one line');
        }

        validator(
            ['lines' => $lines],
            [
                'lines'                => 'required|array|min:2',
                'lines.*.account_id'   => 'required|exists:accounts,id',
                'lines.*.ledger_id'    => 'nullable|exists:ledgers,id',
                'lines.*.debit'        => 'nullable|numeric|min:0',
                'lines.*.credit'       => 'nullable|numeric|min:0',
                'lines.*.journal_class_id' => 'nullable|exists:journal_classes,id',
                'lines.*.remark'       => 'nullable|string',
                'lines.*.remark_fa'    => 'nullable|string',
                'lines.*.remark_ps'    => 'nullable|string',
            ]
        )->validate();
    }

    protected function createLines(Transaction $transaction, array $lines): void
    {
        $totalDebit  = 0;
        $totalCredit = 0;

        foreach ($lines as $line) {
            $debit  = $line['debit']  ?? 0;
            $credit = $line['credit'] ?? 0;

            // XOR rule
            if (($debit > 0 && $credit > 0) || ($debit == 0 && $credit == 0)) {
                throw new Exception('Each transaction line must have either debit OR credit');
            }

            $totalDebit  += $debit;
            $totalCredit += $credit;

            $transaction->lines()->create([
                'account_id' => $line['account_id'],
                'ledger_id' => $line['ledger_id'] ?? null,
                'journal_class_id' => $line['journal_class_id'] ?? null,
                'debit'      => $debit,
                'credit'     => $credit,
                // 'branch_id'  => $transaction->branch_id,
                'remark'     => $line['remark'] ?? null,
                'remark_fa'  => $line['remark_fa'] ?? null,
                'remark_ps'  => $line['remark_ps'] ?? null,
                // 'created_by' => Auth::id(),
            ]);
        }

        if (round($totalDebit, 4) !== round($totalCredit, 4)) {
            throw new Exception('Transaction is not balanced: ' . $totalDebit . ' != ' . $totalCredit);
        }
    }

    // ======================================================
    // 🔁 REVERSAL (AUDIT-SAFE)
    // ======================================================

    public function reverse(Transaction $original, ?string $reason = null, ?string $number = null, ?string $referenceType = null): Transaction
    {
        return DB::transaction(function () use ($original, $reason, $number, $referenceType) {
            $from = TransactionStatus::tryFrom((string) $original->status);

            if ($from !== TransactionStatus::POSTED || !TransactionStateMachine::canTransition($from, TransactionStatus::REVERSED)) {
                throw InvalidStatusTransitionException::for($from ?? TransactionStatus::DRAFT, TransactionStatus::REVERSED);
            }

            $original->loadMissing('lines');
            $reversalRemark = $this->getReversalRemark($original, $number, $referenceType);

            $reversal = Transaction::create([
                'branch_id'      => $original->branch_id,
                'currency_id'    => $original->currency_id,
                'rate'           => $original->rate,
                'date'           => now()->toDateString(),
                'voucher_number' => 'Reversal of ' . $original->voucher_number,
                'reference_type' => 'reversal',
                'reference_id'   => $original->id,
                'remark'         => $reason ?? 'Reversal of transaction ' . $original->id,
                'status'         => TransactionStatus::POSTED->value,
                'reversal_of_id' => $original->id,
                'posted_at'      => now(),
                'posted_by'      => Auth::id(),
                'created_by'     => Auth::id(),
            ]);

            foreach ($original->lines as $line) {
                $reversal->lines()->create([
                    'account_id' => $line->account_id,
                    'ledger_id' => $line->ledger_id,
                    'journal_class_id' => $line->journal_class_id,
                    'debit'      => $line->credit,
                    'credit'     => $line->debit,
                    // 'branch_id'  => $original->branch_id,
                    'remark' => $reversalRemark['remark'],
                    'remark_fa' => $reversalRemark['remark_fa'] ?? null,
                    'remark_ps' => $reversalRemark['remark_ps'] ?? null,
                    // 'created_by' => Auth::id(),
                ]);
            }

            StockMovement::query()
                ->where('reference_type', $original->reference_type)
                ->where('reference_id', $original->reference_id)
                ->get()
                ->each(function (StockMovement $movement) use ($reversal) {
                    $this->stockService->post([
                        'branch_id' => $movement->branch_id,
                        'item_id' => $movement->item_id,
                        'warehouse_id' => $movement->warehouse_id,
                        'unit_measure_id' => $movement->unit_measure_id,
                        'size_id' => $movement->size_id,
                        'movement_type' => $movement->movement_type === StockMovementType::IN
                            ? StockMovementType::OUT->value
                            : StockMovementType::IN->value,
                        'source' => $movement->source,
                        'reference_type' => Transaction::class,
                        'reference_id' => $movement->reference_id,
                        'quantity' => (float) $movement->quantity,
                        'unit_cost' => $movement->unit_cost,
                        'unit_cost_override' => (float) $movement->unit_cost,
                        'batch' => $movement->batch,
                        'expire_date' => $movement->expire_date,
                        'date' => now()->toDateString(),
                        'status' => \App\Enums\StockStatus::VOIDED->value,
                    ]);
                });

            $oldMovements = StockMovement::query()
                ->where('reference_type', $original->reference_type)
                ->where('reference_id', $original->reference_id)
                ->get();

            foreach ($oldMovements as $movement) {
                $movement->update([
                    'status' => \App\Enums\StockStatus::VOIDED->value,
                ]);
            }

            $original->update([
                'status' => TransactionStatus::REVERSED->value,
                'reversed_at' => now(),
                'reversal_reason' => $reason,
                'updated_by' => Auth::id(),
            ]);

            return $reversal;
        });
    }

    private function getReversalRemark(Transaction $original, ?string $number = null, ?string $referenceType = null)
    {
        $remark = '';
        $remark_fa = '';
        $remark_ps = '';

        // Default fallback
        $number = $number ?? ($original->number ?? $original->id);
        $referenceType = $referenceType ?? $original->reference_type;

        // Map core transaction types to their reversal remarks
        switch ($referenceType) {
            case \App\Models\AccountTransfer\AccountTransfer::class:
                $remark = "Reversal of account transfer #" . $number;
                $remark_fa = "برگشتی انتقال حساب #" . $number;
                $remark_ps = "د حساب لیږد بیرته راګرځول #" . $number;
                break;

            case \App\Models\JournalEntry\JournalEntry::class:
                $remark = "Reversal of journal entry #" . $number;
                $remark_fa = "برگشتی ژورنال #" . $number;
                $remark_ps = "د ژورنال داخله بیرته راګرځول #" . $number;
                break;

            case \App\Models\Purchase\Purchase::class:
                $remark = "Reversal of purchase #" . $number;
                $remark_fa = "برگشتی خریداری #" . $number;
                $remark_ps = "د پيرودنې بیرته راګرځول #" . $number;
                break;

            case \App\Models\Sale\Sale::class:
                $remark = "Reversal of sale #" . $number;
                $remark_fa = "برگشتی فروش #" . $number;
                $remark_ps = "د خرڅلاو بیرته راګرځول #" . $number;
                break;

            case \App\Models\Payment\Payment::class:
                $remark = "Reversal of payment #" . $number;
                $remark_fa = "برگشتی پرداخت #" . $number;
                $remark_ps = "د تادیې بیرته راګرځول #" . $number;
                break;

            case \App\Models\Receipt\Receipt::class:
                $remark = "Reversal of receipt #" . $number;
                $remark_fa = "برگشتی رسید #" . $number;
                $remark_ps = "د رسید بیرته راګرځول #" . $number;
                break;

            case \App\Models\Payment\Payment::class:
                $remark = "Reversal of supplier payment #" . $number;
                $remark_fa = "برگشتی پرداخت عرضه کننده #" . $number;
                $remark_ps = "د عرضه کوونکي تادیې بیرته راګرځول #" . $number;
                break;

            case \App\Models\Receipt\Receipt::class:
                $remark = "Reversal of customer receipt #" . $number;
                $remark_fa = "برگشتی رسید مشتری #" . $number;
                $remark_ps = "د پیرودونکي رسید بیرته راګرځول #" . $number;
                break;

            case \App\Models\Inventory\StockAdjustment::class:
                $remark = "Reversal of stock adjustment #" . $number;
                $remark_fa = "برگشتی تعدیل موجودی #" . $number;
                $remark_ps = "د موجودۍ تعدیل بیرته راګرځول #" . $number;
                break;

            case \App\Models\Expense\Expense::class:
                $remark = "Reversal of expense #" . $number;
                $remark_fa = "برگشتی هزینه #" . $number;
                $remark_ps = "د هزینه بیرته راګرځول #" . $number;
                break;
            case \App\Models\Owner\Drawing::class:
                $remark = "Reversal of drawing #" . $number;
                $remark_fa = "برگشتی رسید مشتری #" . $number;
                $remark_ps = "د پیرودونکي رسید بیرته راګرځول #" . $number;
                break;

            default:
                $remark = "Reversal of transaction #" . $number;
                $remark_fa = "برگشت تراکنش #" . $number;
                $remark_ps = "د تراکنش بیرته راګرځول #" . $number;
        }

        return [
            'remark'     => $remark,
            'remark_fa'  => $remark_fa,
            'remark_ps'  => $remark_ps,
        ];

    }
}
