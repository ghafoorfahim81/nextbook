<?php

namespace App\Services\Hr;

use App\Enums\LoanRepaymentSource;
use App\Enums\LoanStatus;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\EmployeeLoanRepayment;
use App\Services\TransactionService;
use App\Support\BranchContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Staff loans and advances: approval, disbursement, and repayment in cash.
 *
 * Recovery THROUGH payroll is not here — it is a credit line inside the payroll
 * accrual, because the money never moves separately: the employee simply
 * receives less. PayrollPostingService owns that. This service owns the two
 * events where cash actually moves.
 *
 * Advances and loans sit in different control accounts on purpose. A salary
 * advance is next month's pay handed over early; a loan is a genuine
 * receivable that may outlive the employment. Netting them into one balance
 * would make either one unreportable.
 */
class EmployeeLoanService
{
    public function __construct(
        private readonly TransactionService $transactions,
    ) {
    }

    /**
     * Send a draft loan for approval.
     */
    public function submit(EmployeeLoan $loan): EmployeeLoan
    {
        $this->assertStatus($loan, [LoanStatus::Draft], 'submitted');

        $loan->forceFill(['status' => LoanStatus::PendingApproval->value])->save();

        return $loan->fresh();
    }

    /**
     * Approve a loan. Approval authorises the money; it does not move it.
     *
     * Kept separate from disburse() because in practice the person who approves
     * a staff loan is not the person who opens the safe, and collapsing the two
     * would put the GL entry under whoever happened to click approve.
     */
    public function approve(EmployeeLoan $loan): EmployeeLoan
    {
        $this->assertStatus($loan, [LoanStatus::Draft, LoanStatus::PendingApproval], 'approved');

        $loan->forceFill([
            'status' => LoanStatus::Approved->value,
            'approved_by' => auth()->id(),
            'approved_at' => now(),
        ])->save();

        return $loan->fresh();
    }

    public function reject(EmployeeLoan $loan, ?string $reason = null): EmployeeLoan
    {
        $this->assertStatus($loan, [LoanStatus::Draft, LoanStatus::PendingApproval], 'rejected');

        $loan->forceFill([
            'status' => LoanStatus::Cancelled->value,
            'remark' => $reason ?? $loan->remark,
        ])->save();

        return $loan->fresh();
    }

    /**
     * Hand the money over and post it.
     *
     *   DR  employee-advances | employee-loans-receivable   ledger_id = employee
     *       CR  cash-in-hand / bank
     *
     * The debit carries the employee's ledger so the loan shows on their
     * statement and on any receivable ageing, exactly as a customer balance
     * would.
     */
    public function disburse(EmployeeLoan $loan, ?string $bankAccountId = null): EmployeeLoan
    {
        $this->assertStatus($loan, [LoanStatus::Approved], 'disbursed');

        $principal = Decimal::amount($loan->principal_amount);

        if (! Decimal::isPositive($principal)) {
            throw PayrollException::make(
                'A loan needs a positive amount before it can be paid out.',
                ['employee_loan_id' => $loan->id]
            );
        }

        $cashAccountId = $bankAccountId ?? $loan->bank_account_id;

        if (! $cashAccountId) {
            throw PayrollException::make(
                'Choose the account the money is paid from.',
                ['employee_loan_id' => $loan->id]
            );
        }

        $employee = $loan->employee;

        if (! $employee?->ledger_id) {
            throw PayrollException::make(
                'This employee has no ledger account to book the loan against.',
                ['employee_loan_id' => $loan->id]
            );
        }

        $controlAccountId = $this->account($loan->accountSlug(), $loan->branch_id);
        $rate = Decimal::rate($loan->rate);

        return DB::transaction(function () use ($loan, $employee, $principal, $rate, $cashAccountId, $controlAccountId) {
            $transaction = $this->transactions->post(
                header: [
                    'currency_id' => $loan->currency_id
                        ?? BranchContext::homeCurrency($loan->branch_id)?->id,
                    'rate' => $rate,
                    'date' => $loan->issue_date->toDateString(),
                    'voucher_number' => 'LOAN-'.$loan->number,
                    'reference_type' => EmployeeLoan::class,
                    'reference_id' => $loan->id,
                    'remark' => $loan->typeEnum()->getLabel().': '.$employee->full_name,
                    'branch_id' => $loan->branch_id,
                ],
                lines: [
                    [
                        'account_id' => $controlAccountId,
                        'ledger_id' => $employee->ledger_id,
                        'currency_id' => $loan->currency_id,
                        'rate' => $rate,
                        'debit' => $principal,
                        'credit' => 0,
                        'base_debit' => Decimal::toBase($principal, $rate),
                        'base_credit' => 0,
                        'remark' => 'Loan issued: '.$employee->full_name,
                        'remark_fa' => 'قرض صادر شده: '.$employee->full_name,
                        'remark_ps' => 'ورکړل شوی پور: '.$employee->full_name,
                    ],
                    [
                        'account_id' => $cashAccountId,
                        'currency_id' => $loan->currency_id,
                        'rate' => $rate,
                        'debit' => 0,
                        'credit' => $principal,
                        'base_debit' => 0,
                        'base_credit' => Decimal::toBase($principal, $rate),
                        'remark' => 'Loan issued: '.$employee->full_name,
                    ],
                ],
            );

            $loan->forceFill([
                'status' => LoanStatus::Active->value,
                'outstanding_amount' => $principal,
                'transaction_id' => $transaction->id,
                'bank_account_id' => $cashAccountId,
            ])->save();

            return $loan->fresh();
        });
    }

    /**
     * Take a repayment in cash rather than through payroll.
     *
     *   DR  cash-in-hand / bank
     *       CR  employee-advances | employee-loans-receivable   ledger_id = employee
     *
     * A plain two-line entry, not a settlement: the loan is one balance being
     * drawn down, not a set of open items to match against. The instalment
     * schedule is a plan, not a list of separately payable claims.
     */
    public function repayInCash(EmployeeLoan $loan, array $attributes): EmployeeLoanRepayment
    {
        if (! $loan->statusEnum()->isDisbursed()) {
            throw PayrollException::make(
                'Only a disbursed loan can be repaid.',
                ['employee_loan_id' => $loan->id, 'status' => $loan->statusEnum()->value]
            );
        }

        $amount = Decimal::amount($attributes['amount']);

        if (! Decimal::isPositive($amount)) {
            throw PayrollException::make(
                'A repayment needs a positive amount.',
                ['employee_loan_id' => $loan->id]
            );
        }

        $outstanding = Decimal::amount($loan->outstanding_amount);

        if (Decimal::cmp($amount, $outstanding) > 0) {
            // Refused rather than parked as a credit: an employee handing back
            // more than they borrowed is a data-entry slip, and silently
            // turning it into a payable to them hides the mistake.
            throw PayrollException::make(
                'That is more than is outstanding on this loan.',
                ['employee_loan_id' => $loan->id, 'outstanding' => $outstanding, 'amount' => $amount]
            );
        }

        $employee = $loan->employee;
        $rate = Decimal::rate($attributes['rate'] ?? $loan->rate);
        $currencyId = $attributes['currency_id'] ?? $loan->currency_id;
        $controlAccountId = $this->account($loan->accountSlug(), $loan->branch_id);

        return DB::transaction(function () use ($loan, $employee, $attributes, $amount, $rate, $currencyId, $controlAccountId) {
            $transaction = $this->transactions->post(
                header: [
                    'currency_id' => $currencyId ?? BranchContext::homeCurrency($loan->branch_id)?->id,
                    'rate' => $rate,
                    'date' => $attributes['date'],
                    'voucher_number' => 'LREP-'.$loan->number,
                    'reference_type' => EmployeeLoanRepayment::class,
                    'reference_id' => null,
                    'remark' => 'Loan repayment: '.($employee?->full_name ?? ''),
                    'branch_id' => $loan->branch_id,
                ],
                lines: [
                    [
                        'account_id' => $attributes['bank_account_id'],
                        'currency_id' => $currencyId,
                        'rate' => $rate,
                        'debit' => $amount,
                        'credit' => 0,
                        'base_debit' => Decimal::toBase($amount, $rate),
                        'base_credit' => 0,
                        'remark' => 'Loan repayment: '.($employee?->full_name ?? ''),
                    ],
                    [
                        'account_id' => $controlAccountId,
                        'ledger_id' => $employee?->ledger_id,
                        'currency_id' => $currencyId,
                        'rate' => $rate,
                        'debit' => 0,
                        'credit' => $amount,
                        'base_debit' => 0,
                        'base_credit' => Decimal::toBase($amount, $rate),
                        'remark' => 'Loan repayment: '.($employee?->full_name ?? ''),
                        'remark_fa' => 'بازپرداخت قرض: '.($employee?->full_name ?? ''),
                        'remark_ps' => 'د پور بیرته ورکړه: '.($employee?->full_name ?? ''),
                    ],
                ],
            );

            $repayment = EmployeeLoanRepayment::create([
                'employee_loan_id' => $loan->id,
                'date' => $attributes['date'],
                'amount' => $amount,
                'currency_id' => $currencyId,
                'rate' => $rate,
                'source' => LoanRepaymentSource::Cash->value,
                'transaction_id' => $transaction->id,
                'remark' => $attributes['remark'] ?? null,
            ]);

            // Recomputed from the repayment rows, which is what flips a fully
            // repaid loan to settled without anyone tracking a counter.
            $loan->refreshOutstanding();

            return $repayment;
        });
    }

    /**
     * Write off what is left, when recovery is abandoned — most often because
     * the employee has left.
     *
     *   DR  staff-benefits-expense
     *       CR  employee-advances | employee-loans-receivable
     *
     * Recorded as a repayment with source `write_off` so the balance derives to
     * zero the same way any other repayment does, and the loan statement shows
     * why it closed.
     */
    public function writeOff(EmployeeLoan $loan, string $date, ?string $reason = null): EmployeeLoan
    {
        if (! $loan->statusEnum()->isDisbursed()) {
            throw PayrollException::make(
                'Only a disbursed loan can be written off.',
                ['employee_loan_id' => $loan->id, 'status' => $loan->statusEnum()->value]
            );
        }

        $outstanding = Decimal::amount($loan->outstanding_amount);

        if (! Decimal::isPositive($outstanding)) {
            throw PayrollException::make(
                'There is nothing left to write off on this loan.',
                ['employee_loan_id' => $loan->id]
            );
        }

        $employee = $loan->employee;
        $rate = Decimal::rate($loan->rate);
        $controlAccountId = $this->account($loan->accountSlug(), $loan->branch_id);
        $expenseAccountId = $this->account('staff-benefits-expense', $loan->branch_id);

        return DB::transaction(function () use ($loan, $employee, $outstanding, $rate, $date, $reason, $controlAccountId, $expenseAccountId) {
            $transaction = $this->transactions->post(
                header: [
                    'currency_id' => $loan->currency_id
                        ?? BranchContext::homeCurrency($loan->branch_id)?->id,
                    'rate' => $rate,
                    'date' => $date,
                    'voucher_number' => 'LWO-'.$loan->number,
                    'reference_type' => EmployeeLoan::class,
                    'reference_id' => $loan->id,
                    'remark' => 'Loan written off: '.($employee?->full_name ?? ''),
                    'branch_id' => $loan->branch_id,
                ],
                lines: [
                    [
                        'account_id' => $expenseAccountId,
                        'currency_id' => $loan->currency_id,
                        'rate' => $rate,
                        'debit' => $outstanding,
                        'credit' => 0,
                        'base_debit' => Decimal::toBase($outstanding, $rate),
                        'base_credit' => 0,
                        'remark' => 'Loan written off: '.($employee?->full_name ?? ''),
                    ],
                    [
                        'account_id' => $controlAccountId,
                        'ledger_id' => $employee?->ledger_id,
                        'currency_id' => $loan->currency_id,
                        'rate' => $rate,
                        'debit' => 0,
                        'credit' => $outstanding,
                        'base_debit' => 0,
                        'base_credit' => Decimal::toBase($outstanding, $rate),
                        'remark' => 'Loan written off: '.($employee?->full_name ?? ''),
                    ],
                ],
            );

            EmployeeLoanRepayment::create([
                'employee_loan_id' => $loan->id,
                'date' => $date,
                'amount' => $outstanding,
                'currency_id' => $loan->currency_id,
                'rate' => $rate,
                'source' => LoanRepaymentSource::WriteOff->value,
                'transaction_id' => $transaction->id,
                'remark' => $reason,
            ]);

            $loan->refreshOutstanding();

            // refreshOutstanding() would call a zero balance `settled`, which
            // reads as "repaid". It was not repaid.
            $loan->forceFill(['status' => LoanStatus::WrittenOff->value])->save();

            return $loan->fresh();
        });
    }

    /**
     * @param  array<int, LoanStatus>  $allowed
     */
    private function assertStatus(EmployeeLoan $loan, array $allowed, string $action): void
    {
        if (! in_array($loan->statusEnum(), $allowed, true)) {
            throw PayrollException::make(
                "This loan cannot be {$action} from its current state.",
                ['employee_loan_id' => $loan->id, 'status' => $loan->statusEnum()->value]
            );
        }
    }

    private function account(string $slug, string $branchId): string
    {
        $id = BranchContext::glAccount($slug, $branchId);

        if (! $id) {
            throw PayrollException::make(
                'This branch is missing a payroll account. Run branch provisioning.',
                ['slug' => $slug, 'branch_id' => $branchId]
            );
        }

        return $id;
    }
}
