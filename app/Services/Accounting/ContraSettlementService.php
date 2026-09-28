<?php

namespace App\Services\Accounting;

use App\Enums\LedgerType;
use App\Models\Ledger\Ledger;
use App\Models\Transaction\Transaction;
use App\Support\BranchContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Offsetting what a party owes us against what we owe them.
 *
 * A trader who both buys from and sells to the same person settles the smaller
 * side against the larger rather than sending money both ways. The two roles
 * are two ledgers — one customer, one supplier — because a ledger's control
 * account is derived from its type, and merging them would put receivables and
 * payables on one balance.
 *
 * Posted as TWO settlement vouchers passing a clearing account between them,
 * rather than as one hand-built journal:
 *
 *   1. The customer's invoices are relieved, the clearing account taking the
 *      place of cash.
 *   2. The supplier's bills are paid out of that same clearing account.
 *
 * The clearing account nets to zero, and the pair leaves the ledger exactly
 * where a single "debit payables, credit receivables" entry would — but with
 * settlement rows on both sides, which a journal entry cannot produce. It also
 * means the whole of SettlementService is reused unchanged: partial
 * allocations, foreign currency and rounding all behave as they do anywhere
 * else, instead of being reimplemented for this one document.
 */
class ContraSettlementService
{
    public function __construct(private readonly SettlementService $settlements)
    {
    }

    /**
     * @param  array<string, mixed>  $voucher   date, currency_id, rate, amount, branch_id,
     *                                          reference_type, reference_id, voucher_number, remark
     * @param  array<int, array<string, mixed>>  $customerAllocations  target_line_id + amount
     * @param  array<int, array<string, mixed>>  $supplierAllocations  target_line_id + amount
     * @return array{customer: Transaction, supplier: Transaction}
     */
    public function offset(
        Ledger $customer,
        Ledger $supplier,
        array $voucher,
        array $customerAllocations = [],
        array $supplierAllocations = [],
    ): array {
        $this->assertRoles($customer, $supplier);

        $branchId = (string) ($voucher['branch_id'] ?? $customer->branch_id);
        $amount = Decimal::amount($voucher['amount']);

        if (! Decimal::isPositive($amount)) {
            throw ValidationException::withMessages([
                'amount' => [__('general.contra_amount_must_be_positive')],
            ]);
        }

        if ($customer->branch_id !== $supplier->branch_id) {
            throw ValidationException::withMessages([
                'supplier_ledger_id' => [__('general.contra_parties_must_share_a_branch')],
            ]);
        }

        $clearingId = BranchContext::glAccount('contra-clearing', $branchId);

        if (! $clearingId) {
            throw ValidationException::withMessages([
                'amount' => [__('general.contra_clearing_account_missing')],
            ]);
        }

        // A set-off can only cover the overlap. Left to itself the settlement
        // engine treats anything beyond the open claims as excess cash and
        // parks it as an advance — sensible for a real payment, wrong here:
        // offsetting 4,000 against a 3,000 debt would invent a 1,000
        // prepayment out of an agreement that never mentioned one.
        $this->assertBothSidesCanCover($customer, $supplier, $voucher, $amount);

        return DB::transaction(function () use (
            $customer, $supplier, $voucher, $customerAllocations,
            $supplierAllocations, $branchId, $amount, $clearingId
        ) {
            // Money "in" from the customer, funded by the clearing account
            // rather than by cash: credits receivables, debits clearing.
            $customerSide = $this->settlements->settle(
                voucher: $this->side($voucher, $customer, $supplier, $branchId, $amount, $clearingId, SettlementService::DIRECTION_IN),
                allocations: $customerAllocations,
            );

            // ...and straight back out to the supplier: debits payables,
            // credits clearing, leaving it at zero.
            $supplierSide = $this->settlements->settle(
                voucher: $this->side($voucher, $supplier, $customer, $branchId, $amount, $clearingId, SettlementService::DIRECTION_OUT),
                allocations: $supplierAllocations,
            );

            $this->assertClearingIsSquare($clearingId, $branchId);

            return ['customer' => $customerSide, 'supplier' => $supplierSide];
        });
    }

    /**
     * One half of the offset, shaped the way SettlementService expects a cash
     * voucher — with the clearing account standing in for the cash.
     *
     * @param  array<string, mixed>  $voucher
     * @return array<string, mixed>
     */
    private function side(
        array $voucher,
        Ledger $ledger,
        Ledger $counterparty,
        string $branchId,
        string $amount,
        string $clearingId,
        string $direction,
    ): array {
        $number = $voucher['voucher_number'] ?? null;

        return array_filter([
            'ledger_id' => $ledger->id,
            'direction' => $direction,
            'date' => $voucher['date'],
            'branch_id' => $branchId,
            'cash_account_id' => $clearingId,
            'cash_currency_id' => (string) $voucher['currency_id'],
            'cash_rate' => Decimal::rate($voucher['rate'] ?? 1),
            'cash_amount' => $amount,
            'voucher_number' => $number,
            'reference_type' => $voucher['reference_type'] ?? null,
            'reference_id' => $voucher['reference_id'] ?? null,
            // Each half names the OTHER account. Read off one party's
            // statement, "set-off against Ahmad (supplier)" is the whole
            // explanation; "set-off" alone leaves the reader hunting for the
            // matching entry.
            'remark' => $voucher['remark']
                ?? trim('Set-off ' . ($number ? '#' . $number . ' ' : '') . 'against ' . $counterparty->name),
            'remark_fa' => trim('تهاتر ' . ($number ? '#' . $number . ' ' : '') . 'در برابر ' . $counterparty->name),
            'remark_ps' => trim('تهاتر ' . ($number ? '#' . $number . ' ' : '') . 'د ' . $counterparty->name . ' پر وړاندې'),
            'exclude_transaction_id' => $voucher['exclude_transaction_id'] ?? null,
        ], fn ($value) => $value !== null);
    }

    /**
     * @param  array<string, mixed>  $voucher
     */
    private function assertBothSidesCanCover(
        Ledger $customer,
        Ledger $supplier,
        array $voucher,
        string $amount,
    ): void {
        $currencyId = (string) $voucher['currency_id'];

        $open = fn (Ledger $ledger, string $direction) => Decimal::amount(
            (string) $this->settlements
                ->openItems($ledger->id, $currencyId, $direction, $voucher['exclude_transaction_id'] ?? null)
                ->sum('remaining_amount')
        );

        $receivable = $open($customer, SettlementService::DIRECTION_IN);

        if (Decimal::cmp($receivable, $amount) < 0) {
            throw ValidationException::withMessages([
                'amount' => [__('general.contra_exceeds_receivable', ['open' => $receivable])],
            ]);
        }

        $payable = $open($supplier, SettlementService::DIRECTION_OUT);

        if (Decimal::cmp($payable, $amount) < 0) {
            throw ValidationException::withMessages([
                'amount' => [__('general.contra_exceeds_payable', ['open' => $payable])],
            ]);
        }
    }

    private function assertRoles(Ledger $customer, Ledger $supplier): void
    {
        if ($this->typeOf($customer) !== LedgerType::CUSTOMER->value) {
            throw ValidationException::withMessages([
                'customer_ledger_id' => [__('general.contra_needs_a_customer_account')],
            ]);
        }

        if ($this->typeOf($supplier) !== LedgerType::SUPPLIER->value) {
            throw ValidationException::withMessages([
                'supplier_ledger_id' => [__('general.contra_needs_a_supplier_account')],
            ]);
        }
    }

    private function typeOf(Ledger $ledger): string
    {
        return (string) ($ledger->type?->value ?? $ledger->type);
    }

    /**
     * The clearing account is the proof. If the two halves did not cancel, the
     * offset is wrong and there is no point letting it commit — a stranded
     * balance here is the one thing nobody would notice by looking at either
     * party's statement.
     */
    private function assertClearingIsSquare(string $clearingId, string $branchId): void
    {
        $balance = (string) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('tl.account_id', $clearingId)
            ->where('t.branch_id', $branchId)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.base_debit - tl.base_credit), 0) AS b')
            ->value('b');

        if (! Decimal::isZero(Decimal::amount($balance))) {
            throw ValidationException::withMessages([
                'amount' => [__('general.contra_did_not_balance', ['balance' => $balance])],
            ]);
        }
    }
}
