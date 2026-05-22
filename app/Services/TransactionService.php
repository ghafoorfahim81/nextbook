<?php

namespace App\Services;

use App\Enums\TransactionStatus;
use App\Models\Transaction\Transaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Exception;

class TransactionService
{
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
                'status'         => $header['status'] ?? 'draft',
                'created_by'     => Auth::id(),
            ]);

            // -----------------------------
            // 3️⃣ Insert lines + enforce balance
            // -----------------------------
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
                    'branch_id'  => $transaction->branch_id,
                    'remark'     => $line['remark'] ?? null,
                    'remark_fa'  => $line['remark_fa'] ?? null,
                    'remark_ps'  => $line['remark_ps'] ?? null,
                    'created_by' => Auth::id(),
                ]);
            }

            // -----------------------------
            // 4️⃣ Double-entry enforcement
            // -----------------------------
            if (round($totalDebit, 4) !== round($totalCredit, 4)) {
                throw new Exception('Transaction is not balanced: ' . $totalDebit . ' != ' . $totalCredit);
            }

            return $transaction;
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

    // ======================================================
    // 🔁 REVERSAL (AUDIT-SAFE)
    // ======================================================

    public function reverse(Transaction $original, ?string $reason = null): Transaction
    {
        return DB::transaction(function () use ($original, $reason) {

            $statusValue = $original->status instanceof \BackedEnum
                ? $original->status->value
                : (string) $original->status;

            if ($statusValue !== 'posted') {
                throw new Exception('Only posted transactions can be reversed');
            }

            $original->loadMissing('lines');

            $reversal = Transaction::create([
                'branch_id'      => $original->branch_id,
                'currency_id'    => $original->currency_id,
                'rate'           => $original->rate,
                'date'           => now()->toDateString(),
                'voucher_number' => $original->voucher_number,
                'reference_type' => 'reversal',
                'reference_id'   => $original->id,
                'remark'         => $reason ?? 'Reversal of ' . ($original->voucher_number ?? $original->id),
                'status'         => TransactionStatus::REVERSED->value,
                'created_by'     => Auth::id(),
            ]);

            foreach ($original->lines as $line) {
                $reversal->lines()->create([
                    'account_id'       => $line->account_id,
                    'ledger_id'        => $line->ledger_id,
                    'journal_class_id' => $line->journal_class_id,
                    'debit'            => $line->credit,
                    'credit'           => $line->debit,
                    'branch_id'        => $reversal->branch_id,
                    'remark'           => 'Reversal: ' . ($line->remark ?? ''),
                    'remark_fa'        => 'معکوس: ' . ($line->remark_fa ?? ''),
                    'remark_ps'        => 'بیرته: ' . ($line->remark_ps ?? ''),
                    'created_by'       => Auth::id(),
                ]);
            }

            $original->update(['status' => 'reversed']);

            return $reversal;
        });
    }
}
