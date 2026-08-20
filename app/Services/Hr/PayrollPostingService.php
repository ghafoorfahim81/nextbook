<?php

namespace App\Services\Hr;

use App\Enums\LoanRepaymentSource;
use App\Enums\PayrollLinePaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\SalaryComponentType;
use App\Exceptions\Hr\PayrollException;
use App\Models\Hr\Attendance;
use App\Models\Hr\EmployeeLoan;
use App\Models\Hr\EmployeeLoanRepayment;
use App\Models\Hr\Payroll;
use App\Models\Hr\PayrollLine;
use App\Models\Hr\PayrollLineComponent;
use App\Models\Hr\SalaryComponent;
use App\Models\Transaction\Transaction;
use App\Services\TransactionService;
use App\Support\BranchContext;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

/**
 * Posts a payroll run to the general ledger, and reverses it.
 *
 * The accrual is one voucher per run:
 *
 *   DR  <salary expense by employment type>   basic + taxable earnings
 *   DR  allowances-commissions                allowances
 *   DR  overtime-expense                      overtime
 *       CR  payroll-liabilities   net payable   ledger_id = employee   PER EMPLOYEE
 *       CR  salary-tax-payable    withheld tax
 *       CR  employee-advances / employee-loans-receivable   recovered   PER EMPLOYEE
 *
 * Expense and tax lines are AGGREGATED to keep the voucher small. The liability
 * lines are deliberately NOT — each carries its employee's ledger_id, which is
 * exactly what SettlementService::openItems() matches on when the salary is
 * later disbursed. Aggregating them would make payment impossible.
 */
class PayrollPostingService
{
    public function __construct(
        private readonly TransactionService $transactions,
    ) {
    }

    /**
     * Move a run through its lifecycle.
     *
     * Posting and reversal have their own methods because they do real work;
     * everything else is a status change the state machine either permits or
     * refuses. Routing them all through one place means the UI can offer
     * exactly the transitions PayrollStatus::allowedTransitions() publishes.
     */
    public function transitionTo(Payroll $payroll, PayrollStatus $target, ?string $reason = null): Payroll
    {
        if ($target === PayrollStatus::Posted) {
            return $this->post($payroll);
        }

        if ($target === PayrollStatus::Reversed) {
            return $this->reverse($payroll, $reason);
        }

        if (! $payroll->canTransitionTo($target)) {
            throw PayrollException::make(
                'This payroll cannot move to that state.',
                [
                    'payroll_id' => $payroll->id,
                    'from' => $payroll->statusEnum()->value,
                    'to' => $target->value,
                ]
            );
        }

        $attributes = ['status' => $target->value];

        if ($target === PayrollStatus::Approved) {
            $attributes['approved_by'] = auth()->id();
            $attributes['approved_at'] = now();
        }

        if ($target === PayrollStatus::Cancelled) {
            $attributes['cancelled_by'] = auth()->id();
            $attributes['cancelled_at'] = now();
            $attributes['cancellation_reason'] = $reason;
        }

        $payroll->forceFill($attributes)->save();

        return $payroll->fresh();
    }

    /**
     * Post the accrual.
     */
    public function post(Payroll $payroll): Payroll
    {
        if (! $payroll->canTransitionTo(PayrollStatus::Posted)) {
            throw PayrollException::make(
                'This payroll cannot be posted from its current state.',
                ['payroll_id' => $payroll->id, 'status' => $payroll->statusEnum()->value]
            );
        }

        $lines = $payroll->lines()->with(['employee', 'components'])->get();

        if ($lines->isEmpty()) {
            throw PayrollException::make(
                'This payroll has no payslips to post.',
                ['payroll_id' => $payroll->id]
            );
        }

        // A payable with no ledger is a credit nobody can ever settle. The
        // observer gives every employee a ledger on creation, so reaching here
        // means something bypassed it — better to refuse the run than to post a
        // liability that can never be paid out.
        $unlinked = $lines->filter(
            fn (PayrollLine $line) => Decimal::isPositive(Decimal::amount($line->net_payable))
                && ! $line->employee?->ledger_id
        );

        if ($unlinked->isNotEmpty()) {
            throw PayrollException::make(
                'Some employees on this payroll have no ledger account.',
                ['employee_ids' => $unlinked->pluck('employee_id')->values()->all()]
            );
        }

        return DB::transaction(function () use ($payroll, $lines) {
            $glLines = $this->buildGlLines($payroll, $lines);

            $transaction = $this->transactions->post(
                header: [
                    'currency_id' => $payroll->currency_id ?? BranchContext::homeCurrency($payroll->branch_id)?->id,
                    'rate' => 1,
                    'date' => $payroll->period_end->toDateString(),
                    'voucher_number' => 'PAY-'.$payroll->number,
                    'reference_type' => Payroll::class,
                    'reference_id' => $payroll->id,
                    'remark' => 'Payroll '.($payroll->period_label ?: $payroll->period_end->toDateString()),
                    'branch_id' => $payroll->branch_id,
                ],
                lines: $glLines,
            );

            $this->linkLiabilityLines($payroll, $lines, $transaction);
            $this->recordLoanRepayments($payroll, $lines);
            $this->lockAttendance($payroll);

            $payroll->forceFill([
                'status' => PayrollStatus::Posted->value,
                'transaction_id' => $transaction->id,
                'posted_by' => auth()->id(),
                'posted_at' => now(),
            ])->save();

            return $payroll->fresh();
        });
    }

    /**
     * @param  \Illuminate\Support\Collection<int, PayrollLine>  $lines
     * @return array<int, array<string, mixed>>
     */
    private function buildGlLines(Payroll $payroll, $lines): array
    {
        $accounts = $this->requiredAccounts($payroll->branch_id);

        // Aggregated per (account, currency, rate) — the same expense in the
        // same currency at the same rate is one line, however many employees
        // contributed to it.
        $expenses = [];
        $taxTotal = [];
        $partyLines = [];
        $recoveryLines = [];

        foreach ($lines as $line) {
            $currencyId = $line->currency_id ?? $payroll->currency_id;
            $rate = Decimal::rate($line->rate);
            $key = fn (string $account) => $account.'|'.$currencyId.'|'.$rate;

            foreach ($line->components as $component) {
                $amount = Decimal::amount($component->amount);

                if (Decimal::isZero($amount)) {
                    continue;
                }

                if ($component->component_type === SalaryComponentType::Earning) {
                    $account = $component->account_id
                        ?? $this->expenseAccountFor($component, $line, $accounts);

                    $k = $key($account);
                    $expenses[$k] = [
                        'account_id' => $account,
                        'currency_id' => $currencyId,
                        'rate' => $rate,
                        'amount' => Decimal::add($expenses[$k]['amount'] ?? '0.0000', $amount),
                    ];
                    continue;
                }

                if ($component->component_code === SalaryComponent::CODE_WAGE_TAX) {
                    $k = $key($accounts['salary-tax-payable']);
                    $taxTotal[$k] = [
                        'account_id' => $accounts['salary-tax-payable'],
                        'currency_id' => $currencyId,
                        'rate' => $rate,
                        'amount' => Decimal::add($taxTotal[$k]['amount'] ?? '0.0000', $amount),
                    ];
                    continue;
                }

                if ($component->component_code === SalaryComponent::CODE_LOAN_RECOVERY) {
                    // Split across the employee's loans, because an advance and
                    // a loan sit in different control accounts.
                    foreach ($this->recoverySplit($line, $amount) as $slug => $portion) {
                        $recoveryLines[] = [
                            'account_id' => $accounts[$slug],
                            'ledger_id' => $line->employee?->ledger_id,
                            'currency_id' => $currencyId,
                            'rate' => $rate,
                            'debit' => 0,
                            'credit' => $portion,
                            'base_debit' => 0,
                            'base_credit' => Decimal::toBase($portion, $rate),
                            'remark' => 'Loan recovery: '.($line->employee?->full_name ?? ''),
                        ];
                    }
                    continue;
                }

                // Unpaid leave and any other deduction reduce the expense
                // rather than creating a liability — the company never owed it.
                $account = $component->account_id
                    ?? $this->expenseAccountFor($component, $line, $accounts);
                $k = $key($account);
                $expenses[$k] = [
                    'account_id' => $account,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'amount' => Decimal::sub($expenses[$k]['amount'] ?? '0.0000', $amount),
                ];
            }

            $net = Decimal::amount($line->net_payable);

            if (Decimal::isPositive($net)) {
                // PER EMPLOYEE with ledger_id — this is what makes the salary
                // an open item the disbursement can settle against.
                $partyLines[] = [
                    'account_id' => $accounts['payroll-liabilities'],
                    'ledger_id' => $line->employee?->ledger_id,
                    'currency_id' => $currencyId,
                    'rate' => $rate,
                    'debit' => 0,
                    'credit' => $net,
                    'base_debit' => 0,
                    'base_credit' => Decimal::toBase($net, $rate),
                    'remark' => 'Salary payable: '.($line->employee?->full_name ?? ''),
                    'remark_fa' => 'معاش قابل پرداخت: '.($line->employee?->full_name ?? ''),
                    'remark_ps' => 'د تادیې وړ معاش: '.($line->employee?->full_name ?? ''),
                ];
            }
        }

        $glLines = [];

        foreach ($expenses as $expense) {
            if (Decimal::isZero($expense['amount'])) {
                continue;
            }

            $glLines[] = [
                'account_id' => $expense['account_id'],
                'currency_id' => $expense['currency_id'],
                'rate' => $expense['rate'],
                'debit' => $expense['amount'],
                'credit' => 0,
                'base_debit' => Decimal::toBase($expense['amount'], $expense['rate']),
                'base_credit' => 0,
                'remark' => 'Payroll expense '.($payroll->period_label ?: ''),
            ];
        }

        foreach ($taxTotal as $tax) {
            if (Decimal::isZero($tax['amount'])) {
                continue;
            }

            $glLines[] = [
                'account_id' => $tax['account_id'],
                'currency_id' => $tax['currency_id'],
                'rate' => $tax['rate'],
                'debit' => 0,
                'credit' => $tax['amount'],
                'base_debit' => 0,
                'base_credit' => Decimal::toBase($tax['amount'], $tax['rate']),
                'remark' => 'Wage tax withheld '.($payroll->period_label ?: ''),
            ];
        }

        return array_merge($glLines, $partyLines, $recoveryLines);
    }

    /**
     * Which control accounts each part of a loan recovery belongs to.
     *
     * @return array<string, string> slug => amount
     */
    private function recoverySplit(PayrollLine $line, string $total): array
    {
        $loans = EmployeeLoan::query()
            ->where('employee_id', $line->employee_id)
            ->recoverableOn($line->payroll->period_end->toDateString())
            ->get();

        $split = [];
        $remaining = $total;

        foreach ($loans as $loan) {
            if (! Decimal::isPositive($remaining)) {
                break;
            }

            $due = $loan->installmentDue();
            $portion = Decimal::cmp($due, $remaining) > 0 ? $remaining : $due;

            if (! Decimal::isPositive($portion)) {
                continue;
            }

            $slug = $loan->accountSlug();
            $split[$slug] = Decimal::add($split[$slug] ?? '0.0000', $portion);
            $remaining = Decimal::sub($remaining, $portion);
        }

        // Anything unattributed falls to advances rather than being dropped —
        // a credit that vanishes would unbalance the voucher.
        if (Decimal::isPositive($remaining)) {
            $split['employee-advances'] = Decimal::add($split['employee-advances'] ?? '0.0000', $remaining);
        }

        return $split;
    }

    /**
     * @param  array<string, string>  $accounts
     */
    private function expenseAccountFor(PayrollLineComponent $component, PayrollLine $line, array $accounts): string
    {
        if ($component->component_code === SalaryComponent::CODE_OVERTIME) {
            return $accounts['overtime-expense'];
        }

        // Anything that is not basic pay is an allowance.
        if ($component->component_code !== 'BASIC') {
            return $accounts['allowances-commissions'];
        }

        $slug = $line->employee?->salaryExpenseSlug() ?? 'permanent-staff-salary';

        return $accounts[$slug] ?? $accounts['permanent-staff-salary'];
    }

    /**
     * @return array<string, string>
     */
    private function requiredAccounts(string $branchId): array
    {
        $slugs = [
            'payroll-liabilities', 'salary-tax-payable', 'employee-advances',
            'employee-loans-receivable', 'overtime-expense', 'allowances-commissions',
            'permanent-staff-salary', 'temporary-staff-salary', 'consultant-professional-salary',
        ];

        $accounts = [];

        foreach ($slugs as $slug) {
            $id = BranchContext::glAccount($slug, $branchId);

            if (! $id) {
                throw PayrollException::make(
                    'This branch is missing a payroll account. Run branch provisioning.',
                    ['slug' => $slug, 'branch_id' => $branchId]
                );
            }

            $accounts[$slug] = $id;
        }

        return $accounts;
    }

    /**
     * Point each payslip at the ledger credit that represents it.
     *
     * Matched on ledger_id, which is exact: there is one payslip per employee
     * per run (enforced by payroll_lines_live_employee_unique) and one ledger
     * per employee, so the liability lines and the payslips are the same set
     * viewed from two sides.
     *
     * Without this a payslip cannot be paid — the disbursement would see an
     * anonymous credit on Payroll Liabilities and have no way to report what it
     * settled.
     *
     * @param  \Illuminate\Support\Collection<int, PayrollLine>  $lines
     */
    private function linkLiabilityLines(Payroll $payroll, $lines, Transaction $transaction): void
    {
        $liabilityAccountId = BranchContext::glAccount('payroll-liabilities', $payroll->branch_id);

        $byLedger = $transaction->lines()
            ->where('account_id', $liabilityAccountId)
            ->whereNotNull('ledger_id')
            ->get()
            ->keyBy('ledger_id');

        foreach ($lines as $line) {
            $ledgerId = $line->employee?->ledger_id;
            $glLine = $ledgerId ? $byLedger->get($ledgerId) : null;

            if (! $glLine) {
                continue;
            }

            $line->forceFill(['liability_line_id' => $glLine->id])->saveQuietly();
        }
    }

    /**
     * Record what each loan actually recovered, so reversing the run can undo
     * exactly its own repayments.
     *
     * @param  \Illuminate\Support\Collection<int, PayrollLine>  $lines
     */
    private function recordLoanRepayments(Payroll $payroll, $lines): void
    {
        foreach ($lines as $line) {
            $recovery = $line->components
                ->firstWhere('component_code', SalaryComponent::CODE_LOAN_RECOVERY);

            if (! $recovery || Decimal::isZero(Decimal::amount($recovery->amount))) {
                continue;
            }

            $remaining = Decimal::amount($recovery->amount);

            $loans = EmployeeLoan::query()
                ->where('employee_id', $line->employee_id)
                ->recoverableOn($payroll->period_end->toDateString())
                ->get();

            foreach ($loans as $loan) {
                if (! Decimal::isPositive($remaining)) {
                    break;
                }

                $due = $loan->installmentDue();
                $portion = Decimal::cmp($due, $remaining) > 0 ? $remaining : $due;

                if (! Decimal::isPositive($portion)) {
                    continue;
                }

                EmployeeLoanRepayment::create([
                    'employee_loan_id' => $loan->id,
                    'payroll_line_id' => $line->id,
                    'date' => $payroll->period_end->toDateString(),
                    'amount' => $portion,
                    'currency_id' => $line->currency_id,
                    'rate' => $line->rate,
                    'source' => LoanRepaymentSource::Payroll->value,
                    'created_by' => auth()->id(),
                ]);

                $loan->refreshOutstanding();
                $remaining = Decimal::sub($remaining, $portion);
            }
        }
    }

    /**
     * Freeze the attendance days this run was calculated from.
     */
    private function lockAttendance(Payroll $payroll): void
    {
        Attendance::query()
            ->forPeriod($payroll->period_start->toDateString(), $payroll->period_end->toDateString())
            ->whereIn('employee_id', $payroll->lines()->pluck('employee_id'))
            ->whereNull('payroll_id')
            ->update(['payroll_id' => $payroll->id]);
    }

    /**
     * Reverse a posted run.
     *
     * Posts the mirror at each line's ORIGINAL rate through
     * TransactionService::reverse(), so no exchange difference is invented, and
     * flips the original voucher to `reversed`. Because
     * SettlementService::openItems() only considers posted transactions, the
     * employee credits stop being open items automatically — that behaviour is
     * relied on rather than re-implemented.
     */
    public function reverse(Payroll $payroll, ?string $reason = null): Payroll
    {
        if (! $payroll->isPosted()) {
            throw PayrollException::make(
                'Only a posted payroll can be reversed.',
                ['payroll_id' => $payroll->id, 'status' => $payroll->statusEnum()->value]
            );
        }

        $paid = $payroll->lines()->where('paid_amount', '>', 0)->exists();

        if ($paid) {
            // Reversing the accrual under a disbursement would leave the cash
            // side pointing at a liability that no longer exists.
            throw PayrollException::make(
                'Void the salary payments before reversing this payroll.',
                ['payroll_id' => $payroll->id]
            );
        }

        if (! $payroll->transaction) {
            throw PayrollException::make(
                'This payroll has no posted voucher to reverse.',
                ['payroll_id' => $payroll->id]
            );
        }

        return DB::transaction(function () use ($payroll, $reason) {
            /** @var Transaction $reversal */
            $reversal = $this->transactions->reverse(
                $payroll->transaction,
                $reason ?? 'Payroll '.$payroll->number.' reversed'
            );

            // Delete this run's repayments and rebuild each loan's balance from
            // what is left, rather than adding the instalment back — derived
            // beats incremented when a correction is involved.
            $lineIds = $payroll->lines()->pluck('id');

            $loanIds = EmployeeLoanRepayment::query()
                ->whereIn('payroll_line_id', $lineIds)
                ->pluck('employee_loan_id')
                ->unique();

            EmployeeLoanRepayment::query()->whereIn('payroll_line_id', $lineIds)->forceDelete();

            EmployeeLoan::query()->whereIn('id', $loanIds)->get()
                ->each(fn (EmployeeLoan $loan) => $loan->refreshOutstanding());

            // Unlock the attendance days so the period can be corrected and
            // re-run.
            Attendance::query()->where('payroll_id', $payroll->id)->update(['payroll_id' => null]);

            // The claim these payslips pointed at is no longer live, so the
            // pointer goes too. Leaving it would let a stale open-item lookup
            // resolve a payslip against a reversed credit.
            PayrollLine::query()->where('payroll_id', $payroll->id)->update([
                'liability_line_id' => null,
                'paid_amount' => 0,
                'payment_status' => PayrollLinePaymentStatus::Unpaid->value,
            ]);

            $payroll->forceFill([
                'status' => PayrollStatus::Reversed->value,
                'reversal_transaction_id' => $reversal->id,
                'cancellation_reason' => $reason,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
            ])->save();

            return $payroll->fresh();
        });
    }
}
