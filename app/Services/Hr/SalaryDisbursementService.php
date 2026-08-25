<?php

namespace App\Services\Hr;

use App\Enums\PaymentMode;
use App\Enums\PayrollLinePaymentStatus;
use App\Exceptions\Hr\PayrollException;
use App\Models\Accounting\Settlement;
use App\Models\Hr\Employee;
use App\Models\Hr\PayrollLine;
use App\Models\Hr\SalaryPayment;
use App\Models\Hr\SalaryPaymentLine;
use App\Models\Transaction\Transaction;
use App\Services\Accounting\SettlementService;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pays employees the salary a posted payroll accrued.
 *
 * This deliberately does NOT post its own voucher. The accrual already left a
 * per-employee CREDIT on Payroll Liabilities, which is precisely the shape
 * SettlementService understands as a payable — so paying a salary is the same
 * operation as paying a supplier, with a different control account:
 *
 *   DR  payroll-liabilities   net   ledger_id = employee
 *       CR  cash-in-hand / bank     net
 *
 * Going through settle() rather than post() brings partial payment, per-payslip
 * open-item matching, automatic FX realisation (an expat accrued at 68 and paid
 * at 71 posts fx-gain/fx-loss on its own) and overpayment parking in Employee
 * Advances — several hundred lines of allocation and exchange logic that would
 * otherwise be re-implemented here and drift from the receipt/payment path.
 *
 * The one thing settle() cannot know is which PAYSLIP a claim belongs to: the
 * accrual is one voucher for the whole run. payroll_lines.liability_line_id
 * closes that, and is what everything below joins on.
 */
class SalaryDisbursementService
{
    public function __construct(
        private readonly SettlementService $settlements,
    ) {
    }

    /**
     * Unpaid payslips for an employee, oldest first, ready for a payment form.
     *
     * Built from the GL rather than from payroll_lines.paid_amount: the ledger
     * is what the money actually did, and the column is a cache of it. If the
     * two ever disagree the form should offer what is genuinely still open.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function openPayslips(
        Employee $employee,
        ?string $currencyId = null,
        ?string $excludeTransactionId = null
    ): Collection {
        if (! $employee->ledger_id) {
            return collect();
        }

        $items = $this->settlements->openItems(
            ledgerId: $employee->ledger_id,
            currencyId: $currencyId,
            direction: SettlementService::DIRECTION_OUT,
            excludeTransactionId: $excludeTransactionId
        );

        if ($items->isEmpty()) {
            return $items;
        }

        $payslips = PayrollLine::query()
            ->with('payroll:id,number,name,period_label,period_start,period_end')
            ->whereIn('liability_line_id', $items->pluck('target_line_id'))
            ->get()
            ->keyBy('liability_line_id');

        return $items->map(function (array $item) use ($payslips) {
            /** @var PayrollLine|null $payslip */
            $payslip = $payslips->get($item['target_line_id']);

            // A claim with no payslip behind it is not an error: an employee
            // can carry an opening balance or a manual journal credit, and
            // those are payable through this same screen.
            return $item + [
                'payroll_line_id' => $payslip?->id,
                'payroll_id' => $payslip?->payroll_id,
                'payroll_number' => $payslip?->payroll?->number,
                'period_label' => $payslip?->payroll?->period_label,
            ];
        });
    }

    /**
     * Post a salary payment and record which payslips it settled.
     *
     * `$allocations` is the SettlementService shape — target_line_id + amount.
     * Empty means FIFO across whatever is open, which is the right default for
     * "pay this person their salary".
     *
     * @param  array<string, mixed>  $attributes
     * @param  array<int, array<string, mixed>>  $allocations
     */
    public function pay(array $attributes, array $allocations = []): SalaryPayment
    {
        /** @var Employee $employee */
        $employee = Employee::query()->findOrFail($attributes['employee_id']);

        if (! $employee->ledger_id) {
            throw PayrollException::make(
                'This employee has no ledger account to pay against.',
                ['employee_id' => $employee->id]
            );
        }

        return DB::transaction(function () use ($employee, $attributes, $allocations) {
            $payment = SalaryPayment::create([
                'number' => $attributes['number'] ?? SalaryPayment::nextNumber(),
                'date' => $attributes['date'],
                'payroll_id' => $attributes['payroll_id'] ?? null,
                'employee_id' => $employee->id,
                'ledger_id' => $employee->ledger_id,
                'currency_id' => $attributes['currency_id'],
                'rate' => $attributes['rate'] ?? 1,
                'amount' => $attributes['amount'],
                // Bill-by-bill when the caller named the payslips, on-account
                // when it did not — which is what actually happened, rather
                // than whatever the form's radio button was left on.
                'payment_mode' => $attributes['payment_mode']
                    ?? ($allocations === [] ? PaymentMode::OnAccount->value : PaymentMode::BillByBill->value),
                'bank_account_id' => $attributes['bank_account_id'],
                'cheque_no' => $attributes['cheque_no'] ?? null,
                'narration' => $attributes['narration'] ?? null,
            ]);

            $transaction = $this->settlements->settle(
                voucher: array_filter([
                    'ledger_id' => $employee->ledger_id,
                    // Money leaving. Stated, never inferred — the direction is
                    // what decides whether the ledger's credit is relieved or
                    // another one is created.
                    'direction' => SettlementService::DIRECTION_OUT,
                    'branch_id' => $employee->branch_id,
                    'date' => $attributes['date'],
                    'cash_account_id' => $attributes['bank_account_id'],
                    'cash_currency_id' => $attributes['currency_id'],
                    'cash_rate' => $attributes['rate'] ?? 1,
                    'cash_amount' => $attributes['amount'],
                    'voucher_number' => $attributes['cheque_no'] ?? 'SAL-'.$payment->number,
                    'reference_type' => SalaryPayment::class,
                    'reference_id' => $payment->id,
                    'remark' => $attributes['narration']
                        ?? "Salary payment #{$payment->number} to {$employee->full_name}",
                    'remark_fa' => 'پرداخت معاش #'.$payment->number.' به '.$employee->full_name,
                    'remark_ps' => $employee->full_name.' ته د #'.$payment->number.' معاش ورکړه',
                ], fn ($value) => $value !== null),
                allocations: $allocations,
            );

            $payment->forceFill(['transaction_id' => $transaction->id])->save();

            $this->recordPaymentLines($payment, $transaction);
            $this->refreshPayslips($transaction->id);

            return $payment->fresh(['lines']);
        });
    }

    /**
     * Void a salary payment and re-open what it settled.
     *
     * Soft-deletes the voucher rather than reversing it, matching what
     * PaymentController::destroy() does to a supplier payment. Reversal is
     * wrong here: it would post a MIRROR credit on Payroll Liabilities carrying
     * the employee's ledger, and openItems() would then offer that credit as a
     * second thing to pay alongside the payslip it just re-opened — the
     * employee would appear owed their salary twice.
     *
     * Reversal is the right tool for an accrual, which is a real event that
     * happened and was later undone. A payment entered by mistake is not an
     * event; it is a typo, and it is recoverable through restore().
     */
    public function void(SalaryPayment $payment): SalaryPayment
    {
        return DB::transaction(function () use ($payment) {
            $transaction = $payment->transaction()->first();

            // Note what it was holding closed BEFORE the settlements go, or
            // there is nothing left to recalculate from.
            $affected = $transaction ? $this->payslipsSettledBy($transaction->id) : [];

            if ($transaction) {
                Settlement::withoutGlobalScopes()
                    ->where('transaction_id', $transaction->id)
                    ->delete();
                $transaction->lines()->delete();
                $transaction->delete();
            }

            $payment->lines()->delete();
            $payment->delete();

            $this->recalculate($affected);

            return $payment;
        });
    }

    /**
     * Put a voided payment back, settlements and all.
     *
     * The mirror of void(), and what makes the Undo toast work end to end.
     */
    public function restore(SalaryPayment $payment): SalaryPayment
    {
        return DB::transaction(function () use ($payment) {
            $transaction = $payment->transaction()->withTrashed()->first();

            if ($transaction) {
                $transaction->restore();
                $transaction->lines()->withTrashed()->restore();
                Settlement::withoutGlobalScopes()
                    ->onlyTrashed()
                    ->where('transaction_id', $transaction->id)
                    ->restore();
            }

            $payment->restore();
            $payment->lines()->withTrashed()->restore();

            if ($transaction) {
                $this->refreshPayslips($transaction->id);
            }

            return $payment->fresh();
        });
    }

    /**
     * Mirror the settlement rows into salary_payment_lines.
     *
     * Duplicated from the GL on purpose: the payroll register and the payslip
     * both need "what did this person actually receive for Asad" without
     * walking transaction_lines, and a settlement row knows the claim it
     * relieved but not the payslip that claim belongs to.
     */
    private function recordPaymentLines(SalaryPayment $payment, Transaction $transaction): void
    {
        $rows = DB::table('settlements')
            ->where('transaction_id', $transaction->id)
            ->whereNull('deleted_at')
            ->get();

        if ($rows->isEmpty()) {
            return;
        }

        $payslips = PayrollLine::query()
            ->whereIn('liability_line_id', $rows->pluck('target_line_id')->filter())
            ->get()
            ->keyBy('liability_line_id');

        foreach ($rows as $row) {
            $payslip = $payslips->get((string) $row->target_line_id);

            if (! $payslip) {
                continue;
            }

            SalaryPaymentLine::create([
                'salary_payment_id' => $payment->id,
                'payroll_line_id' => $payslip->id,
                'employee_id' => $payslip->employee_id,
                'amount' => $row->amount_applied,
                'currency_id' => $row->currency_id,
                // The rate the CLAIM was booked at, not the rate the cash moved
                // at. This line says what the payslip was relieved by, and a
                // payslip is relieved in its own currency at its own rate — the
                // difference between the two rates is the FX the settlement
                // already posted separately.
                'rate' => $row->target_rate,
            ]);
        }
    }

    /**
     * Re-derive paid_amount and the badge on whatever this voucher settled.
     *
     * Driven off settlements rather than off the amounts the form sent, so it
     * stays correct when an allocation was split across rates, partially
     * applied, or dropped — the same reasoning as
     * PaymentController::refreshSettledDocuments().
     */
    public function refreshPayslips(string $transactionId): void
    {
        $this->recalculate($this->payslipsSettledBy($transactionId));
    }

    /**
     * @return array<int, string> payroll_line ids
     */
    private function payslipsSettledBy(string $transactionId): array
    {
        return PayrollLine::query()
            ->whereIn('liability_line_id', function ($query) use ($transactionId) {
                $query->select('target_line_id')
                    ->from('settlements')
                    ->where('transaction_id', $transactionId)
                    ->whereNull('deleted_at');
            })
            ->pluck('id')
            ->all();
    }

    /**
     * @param  array<int, string>  $payrollLineIds
     */
    public function recalculate(array $payrollLineIds): void
    {
        $ids = array_values(array_filter(array_unique($payrollLineIds)));

        if ($ids === []) {
            return;
        }

        $lines = PayrollLine::query()->whereIn('id', $ids)->get();

        $applied = DB::table('settlements as s')
            ->whereIn('s.target_line_id', $lines->pluck('liability_line_id')->filter())
            ->whereNull('s.deleted_at')
            ->selectRaw('s.target_line_id, SUM(s.amount_applied) as applied')
            ->groupBy('s.target_line_id')
            ->pluck('applied', 'target_line_id');

        foreach ($lines as $line) {
            $paid = Decimal::amount($applied[$line->liability_line_id] ?? '0');
            $remaining = Decimal::sub(Decimal::amount($line->net_payable), $paid);

            $line->forceFill([
                'paid_amount' => $paid,
                'payment_status' => $this->status($paid, $remaining)->value,
            ])->saveQuietly();
        }
    }

    private function status(string $paid, string $remaining): PayrollLinePaymentStatus
    {
        if (Decimal::cmp($remaining, '0') <= 0) {
            return PayrollLinePaymentStatus::Paid;
        }

        if (Decimal::isPositive($paid)) {
            return PayrollLinePaymentStatus::Partial;
        }

        return PayrollLinePaymentStatus::Unpaid;
    }
}
