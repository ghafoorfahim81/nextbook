<?php

namespace Tests\Feature\Hr;

use App\Enums\PayrollLinePaymentStatus;
use App\Enums\PayrollStatus;
use App\Enums\TaxPeriod;
use App\Exceptions\Hr\PayrollException;
use App\Models\Administration\Currency;
use App\Models\Hr\Employee;
use App\Models\Hr\Payroll;
use App\Models\Hr\SalaryComponent;
use App\Models\Hr\SalaryPayment;
use App\Models\Hr\SalaryStructure;
use App\Models\Hr\Shift;
use App\Models\Hr\TaxBracket;
use App\Models\Hr\TaxBracketSet;
use App\Models\Transaction\TransactionLine;
use App\Services\Hr\PayrollCalculationService;
use App\Services\Hr\PayrollPostingService;
use App\Services\Hr\SalaryDisbursementService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Paying employees what the payroll accrued.
 *
 * The point of routing this through SettlementService rather than posting a
 * bespoke voucher is that partial payment, FX realisation and overpayment
 * handling come along for free. These tests exist to prove that claim rather
 * than assume it — and to pin the one thing settlement cannot do on its own,
 * which is tell you WHICH payslip a payment relieved.
 */
class SalaryDisbursementFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Employee $employee;

    private const PERIOD_START = '2026-08-01';

    private const PERIOD_END = '2026-08-31';

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $shift = Shift::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'shift_id' => $shift->id,
            'employment_type' => 'permanent',
            'joining_date' => '2024-01-01',
        ]);

        $this->seedTaxSet();
        $this->seedSystemComponents();
    }

    private function seedTaxSet(): TaxBracketSet
    {
        $set = TaxBracketSet::create([
            'name' => 'AF Monthly',
            'period' => TaxPeriod::Monthly->value,
            'effective_from' => '2005-01-01',
            'currency_id' => $this->ctx['currency']->id,
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        foreach (TaxBracketSet::defaultAfghanMonthlyBrackets() as $bracket) {
            TaxBracket::create(array_merge($bracket, [
                'tax_bracket_set_id' => $set->id,
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]));
        }

        return $set->fresh();
    }

    private function seedSystemComponents(): void
    {
        foreach (SalaryComponent::defaultComponents() as $component) {
            SalaryComponent::create(array_merge(['affects_gross' => true, 'is_active' => true], $component, [
                'branch_id' => $this->ctx['branch']->id,
                'created_by' => $this->ctx['user']->id,
            ]));
        }
    }

    private function structureFor(Employee $employee, float $basic, ?string $currencyId = null): SalaryStructure
    {
        return SalaryStructure::create([
            'name' => 'Package '.$employee->code,
            'employee_id' => $employee->id,
            'currency_id' => $currencyId ?? $employee->currency_id,
            'effective_from' => '2024-01-01',
            'basic_salary' => $basic,
            'pay_frequency' => 'monthly',
            'is_active' => true,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
    }

    /** A posted run, which is the only kind that leaves anything to pay. */
    private function postedPayroll(): Payroll
    {
        $payroll = Payroll::create([
            'number' => (string) Payroll::nextNumber(),
            'period_start' => self::PERIOD_START,
            'period_end' => self::PERIOD_END,
            'pay_date' => self::PERIOD_END,
            'period_label' => '1405-05',
            'pay_frequency' => 'monthly',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'status' => PayrollStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $payroll = app(PayrollCalculationService::class)->calculate($payroll);

        $service = app(PayrollPostingService::class);
        $payroll = $service->transitionTo($payroll, PayrollStatus::PendingApproval);
        $payroll = $service->transitionTo($payroll, PayrollStatus::Approved);

        return $service->post($payroll);
    }

    private function disbursement(): SalaryDisbursementService
    {
        return app(SalaryDisbursementService::class);
    }

    private function pay(float $amount, array $overrides = [], array $allocations = []): SalaryPayment
    {
        return $this->disbursement()->pay(array_merge([
            'employee_id' => $this->employee->id,
            'date' => self::PERIOD_END,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'amount' => $amount,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
        ], $overrides), $allocations);
    }

    private function accountId(string $slug): string
    {
        return $this->ctx['accounts'][$slug]->id;
    }

    /** Net on a 50,000 basic: 50,000 - (150 + 10% of 37,500) = 46,100. */
    private const NET = 46100.0;

    // ==================================================
    // OPEN ITEMS
    // ==================================================

    public function test_a_posted_payslip_becomes_an_open_item_naming_its_payroll(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $open = $this->disbursement()->openPayslips($this->employee);

        $this->assertCount(1, $open);
        $this->assertEqualsWithDelta(self::NET, (float) $open[0]['remaining_amount'], 0.01);
        // The whole reason liability_line_id exists: a bare open item would
        // only be able to name the payroll, not the payslip inside it.
        $this->assertSame($payroll->lines()->first()->id, $open[0]['payroll_line_id']);
        $this->assertSame('1405-05', $open[0]['period_label']);
    }

    public function test_an_unposted_payroll_leaves_nothing_to_pay(): void
    {
        $this->structureFor($this->employee, 50000);

        $payroll = Payroll::create([
            'number' => (string) Payroll::nextNumber(),
            'period_start' => self::PERIOD_START,
            'period_end' => self::PERIOD_END,
            'pay_date' => self::PERIOD_END,
            'pay_frequency' => 'monthly',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'status' => PayrollStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);
        app(PayrollCalculationService::class)->calculate($payroll);

        $this->assertCount(0, $this->disbursement()->openPayslips($this->employee));
    }

    // ==================================================
    // POSTING
    // ==================================================

    public function test_paying_a_salary_debits_the_liability_and_credits_cash(): void
    {
        $this->structureFor($this->employee, 50000);
        $this->postedPayroll();

        $payment = $this->pay(self::NET);

        $lines = TransactionLine::query()
            ->where('transaction_id', $payment->transaction_id)
            ->get();

        $liability = $lines->firstWhere('account_id', $this->accountId('payroll-liabilities'));
        $cash = $lines->firstWhere('account_id', $this->accountId('cash-in-hand'));

        $this->assertNotNull($liability);
        $this->assertEqualsWithDelta(self::NET, (float) $liability->debit, 0.01);
        $this->assertNotNull($cash);
        $this->assertEqualsWithDelta(self::NET, (float) $cash->credit, 0.01);

        // The debit must carry the employee's ledger, or the subledger and the
        // control account stop agreeing.
        $this->assertSame($this->employee->ledger_id, $liability->ledger_id);
    }

    public function test_the_voucher_balances(): void
    {
        $this->structureFor($this->employee, 50000);
        $this->postedPayroll();

        $payment = $this->pay(self::NET);

        $lines = TransactionLine::query()->where('transaction_id', $payment->transaction_id)->get();

        $this->assertEqualsWithDelta(
            (float) $lines->sum('base_debit'),
            (float) $lines->sum('base_credit'),
            0.0001
        );
    }

    public function test_full_payment_marks_the_payslip_paid(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $this->pay(self::NET);

        $line = $payroll->lines()->firstOrFail()->fresh();

        $this->assertEqualsWithDelta(self::NET, (float) $line->paid_amount, 0.01);
        $this->assertSame(PayrollLinePaymentStatus::Paid->value, $line->payment_status->value);
        $this->assertCount(0, $this->disbursement()->openPayslips($this->employee));
    }

    public function test_a_payment_records_which_payslip_it_settled(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $payment = $this->pay(self::NET);

        $this->assertCount(1, $payment->lines);
        $this->assertSame($payroll->lines()->first()->id, $payment->lines->first()->payroll_line_id);
        $this->assertEqualsWithDelta(self::NET, (float) $payment->lines->first()->amount, 0.01);
    }

    // ==================================================
    // PARTIAL PAYMENT
    // ==================================================

    public function test_a_partial_payment_leaves_the_remainder_open(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $this->pay(20000);

        $line = $payroll->lines()->firstOrFail()->fresh();
        $this->assertSame(PayrollLinePaymentStatus::Partial->value, $line->payment_status->value);
        $this->assertEqualsWithDelta(20000, (float) $line->paid_amount, 0.01);

        $open = $this->disbursement()->openPayslips($this->employee);
        $this->assertCount(1, $open);
        $this->assertEqualsWithDelta(self::NET - 20000, (float) $open[0]['remaining_amount'], 0.01);
    }

    public function test_two_partial_payments_close_the_payslip(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $this->pay(20000);
        $this->pay(self::NET - 20000);

        $line = $payroll->lines()->firstOrFail()->fresh();

        $this->assertSame(PayrollLinePaymentStatus::Paid->value, $line->payment_status->value);
        $this->assertEqualsWithDelta(self::NET, (float) $line->paid_amount, 0.01);
    }

    /**
     * The badge is derived from settlements, not from what the form said it
     * paid — so tampering with the cached column does not survive a refresh.
     */
    public function test_the_paid_badge_is_rebuilt_from_the_ledger(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $payment = $this->pay(20000);

        $payroll->lines()->update([
            'paid_amount' => 999999,
            'payment_status' => PayrollLinePaymentStatus::Paid->value,
        ]);

        $this->disbursement()->refreshPayslips($payment->transaction_id);

        $line = $payroll->lines()->firstOrFail()->fresh();
        $this->assertEqualsWithDelta(20000, (float) $line->paid_amount, 0.01);
        $this->assertSame(PayrollLinePaymentStatus::Partial->value, $line->payment_status->value);
    }

    // ==================================================
    // MULTI-PAYSLIP ALLOCATION
    // ==================================================

    public function test_a_payment_covering_two_months_settles_the_older_one_first(): void
    {
        $this->structureFor($this->employee, 50000);

        $july = $this->runFor('2026-07-01', '2026-07-31', '1405-04');
        $august = $this->postedPayroll();

        // Enough for July in full and a slice of August.
        $this->pay(self::NET + 10000);

        $this->assertSame(
            PayrollLinePaymentStatus::Paid->value,
            $july->lines()->firstOrFail()->fresh()->payment_status->value
        );
        $this->assertSame(
            PayrollLinePaymentStatus::Partial->value,
            $august->lines()->firstOrFail()->fresh()->payment_status->value
        );
    }

    public function test_an_explicit_allocation_can_skip_the_older_payslip(): void
    {
        $this->structureFor($this->employee, 50000);

        $july = $this->runFor('2026-07-01', '2026-07-31', '1405-04');
        $august = $this->postedPayroll();

        $augustLine = $august->lines()->firstOrFail();

        // "This is for August" — an employer paying the current month while an
        // older one is still under dispute is ordinary, and quietly applying it
        // to July would produce a payslip history nobody recognises.
        $this->pay(self::NET, allocations: [[
            'target_line_id' => $augustLine->liability_line_id,
            'amount' => self::NET,
        ]]);

        $this->assertSame(
            PayrollLinePaymentStatus::Unpaid->value,
            $july->lines()->firstOrFail()->fresh()->payment_status->value
        );
        $this->assertSame(
            PayrollLinePaymentStatus::Paid->value,
            $augustLine->fresh()->payment_status->value
        );
    }

    private function runFor(string $start, string $end, string $label): Payroll
    {
        $payroll = Payroll::create([
            'number' => (string) Payroll::nextNumber(),
            'period_start' => $start,
            'period_end' => $end,
            'pay_date' => $end,
            'period_label' => $label,
            'pay_frequency' => 'monthly',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'status' => PayrollStatus::Draft->value,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
        ]);

        $payroll = app(PayrollCalculationService::class)->calculate($payroll);

        $service = app(PayrollPostingService::class);
        $payroll = $service->transitionTo($payroll, PayrollStatus::PendingApproval);
        $payroll = $service->transitionTo($payroll, PayrollStatus::Approved);

        return $service->post($payroll);
    }

    // ==================================================
    // FOREIGN CURRENCY
    // ==================================================

    /**
     * An expat accrued at 68 and paid at 71.
     *
     * The company settles a 1,000 USD debt with cash worth more AFN than the
     * debt was booked at, so it loses on the movement. Nothing in the payroll
     * code computes this — it falls out of the settlement path.
     */
    public function test_paying_a_foreign_salary_at_a_moved_rate_realises_exchange_loss(): void
    {
        $usd = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'code' => 'USD',
            'name' => 'US Dollar',
            // Pinned, because the payslip is booked at the CURRENCY's rate and
            // the factory otherwise randomises it across 0.1–500. Whether this
            // run realises a gain or a loss is the whole point of the test, so
            // it cannot be left to chance.
            'exchange_rate' => 68,
        ]);

        $expat = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $usd->id,
            'employment_type' => 'permanent',
            'joining_date' => '2024-01-01',
            'is_tax_exempt' => true,
        ]);

        $this->structureFor($expat, 1000, $usd->id);
        $this->employee->update(['is_active' => false, 'employment_status' => 'resigned']);

        $payroll = $this->postedPayroll();
        $payslip = $payroll->lines()->where('employee_id', $expat->id)->firstOrFail();

        $this->assertEqualsWithDelta(1000, (float) $payslip->net_payable, 0.01);

        $payment = $this->disbursement()->pay([
            'employee_id' => $expat->id,
            'date' => self::PERIOD_END,
            'currency_id' => $usd->id,
            'rate' => 71,
            'amount' => 1000,
            'bank_account_id' => $this->accountId('cash-in-hand'),
        ]);

        $lines = TransactionLine::query()->where('transaction_id', $payment->transaction_id)->get();

        $fxLoss = $lines->firstWhere('account_id', $this->accountId('fx-loss'));
        $this->assertNotNull($fxLoss, 'Paying above the booked rate should realise a loss.');

        $this->assertEqualsWithDelta(
            (float) $lines->sum('base_debit'),
            (float) $lines->sum('base_credit'),
            0.0001
        );

        $this->assertSame(
            PayrollLinePaymentStatus::Paid->value,
            $payslip->fresh()->payment_status->value
        );
    }

    // ==================================================
    // OVERPAYMENT
    // ==================================================

    public function test_paying_more_than_is_owed_parks_the_excess_in_employee_advances(): void
    {
        $this->structureFor($this->employee, 50000);
        $this->postedPayroll();

        $payment = $this->pay(self::NET + 5000);

        $advance = TransactionLine::query()
            ->where('transaction_id', $payment->transaction_id)
            ->where('account_id', $this->accountId('employee-advances'))
            ->first();

        $this->assertNotNull($advance, 'The excess should become an advance, not a bigger salary.');
        $this->assertEqualsWithDelta(5000, (float) $advance->debit, 0.01);
        $this->assertSame($this->employee->ledger_id, $advance->ledger_id);
    }

    // ==================================================
    // REVERSAL INTERACTION
    // ==================================================

    public function test_a_paid_payroll_cannot_be_reversed(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $this->pay(20000);

        $this->expectException(PayrollException::class);
        app(PayrollPostingService::class)->reverse($payroll->fresh(), 'correction');
    }

    public function test_voiding_a_payment_reopens_the_payslip_and_frees_the_payroll(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $payment = $this->pay(20000);
        $this->disbursement()->void($payment);

        $line = $payroll->lines()->firstOrFail()->fresh();
        $this->assertEqualsWithDelta(0, (float) $line->paid_amount, 0.01);
        $this->assertSame(PayrollLinePaymentStatus::Unpaid->value, $line->payment_status->value);

        $open = $this->disbursement()->openPayslips($this->employee);
        $this->assertCount(1, $open);
        $this->assertEqualsWithDelta(self::NET, (float) $open[0]['remaining_amount'], 0.01);

        // And with nothing paid, the accrual itself can now be corrected.
        $reversed = app(PayrollPostingService::class)->reverse($payroll->fresh(), 'correction');
        $this->assertSame(PayrollStatus::Reversed, $reversed->statusEnum());
    }

    public function test_restoring_a_voided_payment_puts_the_settlement_back(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        $payment = $this->pay(20000);
        $this->disbursement()->void($payment);
        $this->disbursement()->restore($payment);

        $line = $payroll->lines()->firstOrFail()->fresh();
        $this->assertEqualsWithDelta(20000, (float) $line->paid_amount, 0.01);
        $this->assertSame(PayrollLinePaymentStatus::Partial->value, $line->payment_status->value);

        $open = $this->disbursement()->openPayslips($this->employee);
        $this->assertEqualsWithDelta(self::NET - 20000, (float) $open[0]['remaining_amount'], 0.01);
    }

    public function test_reversing_a_payroll_withdraws_its_payslips_from_the_open_list(): void
    {
        $this->structureFor($this->employee, 50000);
        $payroll = $this->postedPayroll();

        app(PayrollPostingService::class)->reverse($payroll, 'correction');

        $this->assertCount(0, $this->disbursement()->openPayslips($this->employee));
    }

    // ==================================================
    // GUARDS
    // ==================================================

    public function test_an_employee_with_no_ledger_cannot_be_paid(): void
    {
        $this->structureFor($this->employee, 50000);
        $this->postedPayroll();

        $this->employee->forceFill(['ledger_id' => null])->saveQuietly();

        $this->expectException(PayrollException::class);
        $this->pay(1000);
    }
}
