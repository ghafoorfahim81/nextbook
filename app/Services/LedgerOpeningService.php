<?php

namespace App\Services;

use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use App\Support\BranchContext;

/**
 * Opening balances for a customer/supplier.
 *
 * `transactions.currency_id` holds one currency for the whole voucher, so a party
 * that owes in several currencies gets one posted transaction per currency rather
 * than one voucher with mixed-currency lines. Each stays balanced in its own
 * currency, and every balance query already converts with `* transactions.rate`,
 * so the home-currency total falls out without touching the reporting layer.
 */
class LedgerOpeningService
{
    public function __construct(private TransactionService $transactions)
    {
    }

    /**
     * Replace all of a ledger's openings with the supplied set.
     *
     * @param  array<int, array{currency_id: string, rate: float|string, amount: float|string, remark?: string|null, date?: string|null}>  $openings
     */
    public function sync(Ledger $ledger, array $openings): void
    {
        DB::transaction(function () use ($ledger, $openings) {
            $this->clear($ledger);

            foreach ($this->usableRows($openings) as $row) {
                $this->post($ledger, $row);
            }
        });
    }

    /**
     * Drop every opening and its posted transaction.
     *
     * Force-deleted rather than soft-deleted: an opening is rebuilt on every edit
     * of the party, so soft deletes would pile up trashed vouchers in the deleted
     * records screen for what the user experiences as a single figure being fixed.
     */
    public function clear(Ledger $ledger): void
    {
        $transactionIds = $ledger->openings()->pluck('transaction_id')->filter()->all();

        DB::transaction(function () use ($ledger, $transactionIds) {
            // Openings first — they carry the FK to transactions.
            $ledger->openings()->forceDelete();

            if ($transactionIds !== []) {
                TransactionLine::whereIn('transaction_id', $transactionIds)->forceDelete();
                Transaction::whereIn('id', $transactionIds)->forceDelete();
            }
        });
    }

    /**
     * Rows the user actually filled in. The form renders every currency, so most
     * rows come back blank and must not be posted as zero-value vouchers.
     *
     * @param  array<int, mixed>  $openings
     * @return array<int, array<string, mixed>>
     */
    private function usableRows(array $openings): array
    {
        return collect($openings)
            ->filter(fn ($row) => is_array($row)
                && filled($row['currency_id'] ?? null)
                && (float) ($row['amount'] ?? 0) > 0)
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $row
     */
    private function post(Ledger $ledger, array $row): void
    {
        $glAccounts = BranchContext::glAccounts();
        $isSupplier = (string) ($ledger->type?->value ?? $ledger->type) === 'supplier';

        $partyAccountId = $isSupplier
            ? ($glAccounts['account-payable'] ?? null)
            : ($glAccounts['account-receivable'] ?? null);
        $equityId = $glAccounts['opening-balance-equity'] ?? null;

        if (! $partyAccountId || ! $equityId) {
            throw new RuntimeException('System accounts (AR/AP or opening balance equity) are missing.');
        }

        $amount = (float) $row['amount'];
        $remark = trim((string) ($row['remark'] ?? ''));
        $labels = $this->remarks($ledger, $isSupplier);

        // The party line carries the user's remark; the equity side keeps the
        // generated label so the GL stays readable.
        $partyLine = [
            'account_id' => $partyAccountId,
            'ledger_id' => $ledger->id,
            'debit' => $isSupplier ? 0 : $amount,
            'credit' => $isSupplier ? $amount : 0,
            'remark' => $remark !== '' ? $remark : $labels['en'],
            'remark_fa' => $remark !== '' ? $remark : $labels['fa'],
            'remark_ps' => $remark !== '' ? $remark : $labels['ps'],
        ];

        $equityLine = [
            'account_id' => $equityId,
            'debit' => $isSupplier ? $amount : 0,
            'credit' => $isSupplier ? 0 : $amount,
            'remark' => $labels['en'],
            'remark_fa' => $labels['fa'],
            'remark_ps' => $labels['ps'],
        ];

        $transaction = $this->transactions->post(
            header: [
                'currency_id' => $row['currency_id'],
                'rate' => (float) $row['rate'],
                'date' => $row['date'] ?? now()->toDateString(),
                'reference_type' => Ledger::class,
                'reference_id' => $ledger->id,
                'remark' => $labels['en'],
            ],
            lines: $isSupplier
                ? [$equityLine, $partyLine]
                : [$partyLine, $equityLine],
        );

        $ledger->openings()->create([
            'transaction_id' => $transaction->id,
        ]);
    }

    /**
     * @return array{en: string, fa: string, ps: string}
     */
    private function remarks(Ledger $ledger, bool $isSupplier): array
    {
        return [
            'en' => 'Opening balance for ' . ($isSupplier ? 'supplier ' : 'customer ') . $ledger->name,
            'fa' => 'موجودی اولیه برای ' . ($isSupplier ? 'تامین کننده ' : 'مشتری ') . $ledger->name,
            'ps' => 'د' . ' ' . $ledger->name . ' ' . 'د پرانیستلو بیلانس ',
        ];
    }
}
