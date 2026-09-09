<?php

namespace Tests\Feature\Accounting;

use App\Enums\CalendarType;
use App\Enums\FinancialPeriodStatus;
use App\Exceptions\Accounting\ClosedPeriodException;
use App\Exceptions\Accounting\SettlementException;
use App\Models\Accounting\FinancialPeriod;
use App\Models\Accounting\FiscalYear;
use App\Models\Accounting\Settlement;
use App\Models\Administration\Currency;
use App\Models\Role;
use App\Models\Transaction\Transaction;
use App\Models\User;
use App\Services\Accounting\FiscalYearService;
use App\Services\Accounting\SettlementService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Permission;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Financial years, the months inside them, and the lock they put on posting.
 *
 * Two things are being pinned down here. First, that a financial year starts
 * where the COMPANY says it does — 1 Jadi, 1 Hamal and 1 January are all real
 * answers among the users of this system, and the twelve months have to tile
 * whichever one is chosen without leaving a day uncovered. Second, that a
 * closed month actually refuses a posting, at the one boundary every module
 * posts through, rather than in each controller that remembers to ask.
 */
class FiscalYearPeriodLockTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private TransactionService $transactions;

    private FiscalYearService $fiscalYears;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->transactions = app(TransactionService::class);
        $this->fiscalYears = app(FiscalYearService::class);
    }

    // ------------------------------------------------------
    // Fixtures
    // ------------------------------------------------------

    private function branchId(): string
    {
        return $this->ctx['branch']->id;
    }

    private function account(string $slug): string
    {
        return $this->ctx['accounts'][$slug]->id;
    }

    private function useCalendar(CalendarType $calendar, int $month, int $day): void
    {
        $this->ctx['company']->update([
            'calendar_type' => $calendar->value,
            'fiscal_year_start_month' => $month,
            'fiscal_year_start_day' => $day,
        ]);

        // The service reads settings off the acting user's company.
        $this->ctx['user']->refresh()->load('company');
        $this->actingAs($this->ctx['user']->fresh());
    }

    /**
     * A user who is NOT super-admin, since super-admin bypasses every gate —
     * including the period-lock override — via the Gate::before in
     * AuthServiceProvider. Only an ordinary user actually meets the lock.
     */
    private function ordinaryUser(array $permissions = []): User
    {
        $user = User::factory()->create([
            'email' => 'clerk-'.fake()->unique()->numberBetween(1000, 9999).'@example.test',
            'branch_id' => $this->branchId(),
            'company_id' => $this->ctx['company']->id,
            'preferences' => User::DEFAULT_PREFERENCES,
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'period-test-clerk', 'guard_name' => 'web'],
            ['slug' => 'period-test-clerk']
        );

        $user->assignRole($role);

        foreach ($permissions as $permission) {
            $user->givePermissionTo(
                Permission::query()->firstOrCreate(
                    ['name' => $permission, 'guard_name' => 'web']
                )
            );
        }

        return $user;
    }

    /**
     * A balanced two-line journal on a date. The simplest thing that posts.
     */
    private function postOn(string $date): Transaction
    {
        return $this->transactions->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => $date,
                'remark' => 'period test',
            ],
            lines: [
                ['account_id' => $this->account('cash-in-hand'), 'debit' => 100],
                ['account_id' => $this->account('retained-earnings'), 'credit' => 100],
            ],
        );
    }

    private function closePeriodCovering(string $date): FinancialPeriod
    {
        $this->fiscalYears->ensureYearFor($date, $this->branchId());
        $period = $this->fiscalYears->periodFor($date, $this->branchId());

        return $this->fiscalYears->closePeriod($period);
    }

    // ------------------------------------------------------
    // Where a year starts
    // ------------------------------------------------------

    public function test_a_gregorian_company_gets_a_january_to_december_year(): void
    {
        $this->useCalendar(CalendarType::GREGORIAN, 1, 1);

        $year = $this->fiscalYears->generate('2026-05-04', $this->branchId());

        $this->assertSame('2026-01-01', $year->start_date->toDateString());
        $this->assertSame('2026-12-31', $year->end_date->toDateString());
        $this->assertSame('2026', $year->name);
    }

    /**
     * Afghanistan's current state fiscal year: 1 Jadi to 30 Qaws. It is named
     * for the Jalali year it ENDS in, which is what an Afghan accountant calls
     * it — the year beginning 1 Jadi 1404 is fiscal year 1405.
     */
    public function test_a_jalali_company_starting_on_jadi_gets_the_afghan_fiscal_year(): void
    {
        $this->useCalendar(CalendarType::JALALI, 10, 1);

        $year = $this->fiscalYears->generate('2026-05-04', $this->branchId());

        $this->assertSame('2025-12-22', $year->start_date->toDateString());
        $this->assertSame('2026-12-21', $year->end_date->toDateString());
        $this->assertSame('1405', $year->name);
    }

    /** The pre-1391 Afghan year, still kept by businesses that never moved. */
    public function test_a_jalali_company_starting_on_hamal_gets_the_nowruz_year(): void
    {
        $this->useCalendar(CalendarType::JALALI, 1, 1);

        $year = $this->fiscalYears->generate('2026-05-04', $this->branchId());

        $this->assertSame('2026-03-21', $year->start_date->toDateString());
        $this->assertSame('2027-03-20', $year->end_date->toDateString());
        $this->assertSame('1405', $year->name);
    }

    /**
     * No gap and no overlap. A single uncovered day is a day on which the guard
     * can find no period and silently lets anything through.
     */
    public function test_the_twelve_months_tile_the_year_exactly(): void
    {
        $this->useCalendar(CalendarType::JALALI, 10, 1);

        $year = $this->fiscalYears->generate('2026-05-04', $this->branchId());
        $periods = $year->periods()->orderBy('start_date')->get();

        $this->assertCount(12, $periods);
        $this->assertSame($year->start_date->toDateString(), $periods->first()->start_date->toDateString());
        $this->assertSame($year->end_date->toDateString(), $periods->last()->end_date->toDateString());

        // skip() preserves keys, so $index is this period's own position.
        foreach ($periods->skip(1) as $index => $period) {
            $previous = $periods[$index - 1];

            $this->assertSame(
                $previous->end_date->copy()->addDay()->toDateString(),
                $period->start_date->toDateString(),
                "period {$period->name} does not start the day after {$previous->name} ends"
            );
        }
    }

    public function test_month_names_use_afghan_not_iranian_jalali_names(): void
    {
        $this->useCalendar(CalendarType::JALALI, 1, 1);

        $year = $this->fiscalYears->generate('2026-05-04', $this->branchId());

        $this->assertStringStartsWith('حمل', $year->periods()->orderBy('start_date')->first()->name);
    }

    // ------------------------------------------------------
    // The lock
    // ------------------------------------------------------

    public function test_posting_into_an_open_period_succeeds(): void
    {
        $this->actingAs($this->ordinaryUser());

        $transaction = $this->postOn('2026-05-04');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_posting_into_a_closed_period_is_refused(): void
    {
        $this->closePeriodCovering('2026-05-04');
        $this->actingAs($this->ordinaryUser());

        $this->expectException(ClosedPeriodException::class);

        $this->postOn('2026-05-04');
    }

    /** The lock is on the voucher's own date, not on today. */
    public function test_a_neighbouring_open_period_still_accepts_postings(): void
    {
        $this->useCalendar(CalendarType::GREGORIAN, 1, 1);
        $this->closePeriodCovering('2026-05-04');
        $this->actingAs($this->ordinaryUser());

        $transaction = $this->postOn('2026-06-04');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    public function test_the_override_permission_allows_posting_into_a_closed_period(): void
    {
        $this->closePeriodCovering('2026-05-04');
        $this->actingAs($this->ordinaryUser(['financial_periods.post_to_closed']));

        $transaction = $this->postOn('2026-05-04');

        $this->assertDatabaseHas('transactions', ['id' => $transaction->id]);
    }

    /**
     * Closing a year shuts every month in it. A closed year with open months
     * inside would show twelve rows that look postable and are not.
     */
    public function test_closing_a_year_closes_its_months(): void
    {
        $year = $this->fiscalYears->ensureYearFor('2026-05-04', $this->branchId());

        $this->fiscalYears->closeYear($year);

        $this->assertSame(
            0,
            $year->periods()->where('status', FinancialPeriodStatus::Open->value)->count()
        );
    }

    /** The year is the backstop: a reopened month inside a closed year is still shut. */
    public function test_a_month_cannot_be_reopened_while_its_year_is_closed(): void
    {
        $year = $this->fiscalYears->ensureYearFor('2026-05-04', $this->branchId());
        $this->fiscalYears->closeYear($year);

        $period = $this->fiscalYears->periodFor('2026-05-04', $this->branchId());

        $this->expectException(ClosedPeriodException::class);

        $this->fiscalYears->reopenPeriod($period);
    }

    public function test_reopening_a_year_then_its_month_restores_posting(): void
    {
        $year = $this->fiscalYears->ensureYearFor('2026-05-04', $this->branchId());
        $this->fiscalYears->closeYear($year);

        $this->fiscalYears->reopenYear($year->refresh());
        $this->fiscalYears->reopenPeriod(
            $this->fiscalYears->periodFor('2026-05-04', $this->branchId())
        );

        $this->actingAs($this->ordinaryUser());

        $this->assertDatabaseHas('transactions', [
            'id' => $this->postOn('2026-05-04')->id,
        ]);
    }

    /**
     * A date nobody has generated a year for is not a lock — it is a gap. The
     * year is created open and the posting goes through, so the first invoice
     * of a new year does not need an administrator first.
     */
    public function test_a_date_with_no_year_yet_generates_one_and_posts(): void
    {
        $this->actingAs($this->ordinaryUser());

        $this->postOn('2031-07-04');

        $this->assertNotNull($this->fiscalYears->yearFor('2031-07-04', $this->branchId()));
    }

    // ------------------------------------------------------
    // Voiding
    // ------------------------------------------------------

    public function test_voiding_removes_the_voucher_and_its_lines(): void
    {
        $transaction = $this->postOn('2026-05-04');

        $this->transactions->void($transaction);

        $this->assertSoftDeleted('transactions', ['id' => $transaction->id]);
        $this->assertSoftDeleted('transaction_lines', ['transaction_id' => $transaction->id]);
    }

    public function test_voiding_inside_a_closed_period_is_refused(): void
    {
        $transaction = $this->postOn('2026-05-04');
        $this->closePeriodCovering('2026-05-04');
        $this->actingAs($this->ordinaryUser());

        $this->expectException(ClosedPeriodException::class);

        $this->transactions->void($transaction->refresh());
    }

    /**
     * The bug this method exists for. An opening balance settled by a receipt
     * cannot be pulled out from under it: the settlements row keeps a foreign
     * key to the line, so the old hand-rolled delete raised a constraint
     * violation, and a soft delete stranded the receipt instead.
     */
    public function test_voiding_a_voucher_another_receipt_has_settled_is_refused(): void
    {
        $settlements = app(SettlementService::class);
        $customer = $this->ctx['customer_ledger'];
        $afn = $this->ctx['currency'];

        $invoice = $this->transactions->post(
            header: [
                'currency_id' => $afn->id,
                'rate' => 1,
                'date' => '2026-05-04',
                'remark' => 'invoice',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $customer->id,
                    'debit' => 5000,
                ],
                ['account_id' => $this->account('product-income'), 'credit' => 5000],
            ],
        );

        $settlements->settle(
            voucher: [
                'ledger_id' => $customer->id,
                'direction' => SettlementService::DIRECTION_IN,
                'date' => '2026-05-10',
                'cash_account_id' => $this->account('cash-in-hand'),
                'cash_currency_id' => $afn->id,
                'cash_rate' => 1,
                'cash_amount' => 5000,
            ],
            allocations: [],
        );

        $this->assertSame(1, Settlement::withoutGlobalScopes()->count());

        $this->expectException(SettlementException::class);

        $this->transactions->void($invoice->refresh());
    }

    /**
     * The other half: voiding the RECEIPT is fine, and takes the settlements it
     * wrote with it so the invoice opens back up.
     */
    public function test_voiding_a_receipt_releases_the_invoice_it_settled(): void
    {
        $settlements = app(SettlementService::class);
        $customer = $this->ctx['customer_ledger'];
        $afn = $this->ctx['currency'];

        $this->transactions->post(
            header: [
                'currency_id' => $afn->id,
                'rate' => 1,
                'date' => '2026-05-04',
                'remark' => 'invoice',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $customer->id,
                    'debit' => 5000,
                ],
                ['account_id' => $this->account('product-income'), 'credit' => 5000],
            ],
        );

        $receipt = $settlements->settle(
            voucher: [
                'ledger_id' => $customer->id,
                'direction' => SettlementService::DIRECTION_IN,
                'date' => '2026-05-10',
                'cash_account_id' => $this->account('cash-in-hand'),
                'cash_currency_id' => $afn->id,
                'cash_rate' => 1,
                'cash_amount' => 5000,
            ],
            allocations: [],
        );

        $this->assertCount(0, $settlements->openItems($customer->id, $afn->id));

        $this->transactions->void($receipt);

        $open = $settlements->openItems($customer->id, $afn->id);

        $this->assertCount(1, $open);
        $this->assertSame('5000.0000', $open->first()['remaining_amount']);
    }

    // ------------------------------------------------------
    // Reversal
    // ------------------------------------------------------

    /**
     * Regression: reverse() rebuilt the header without carrying is_cross_currency
     * across, so the mirrored lines failed the per-currency balance check the
     * original had been exempted from — a cross-currency settlement could be
     * posted but never undone.
     */
    public function test_a_cross_currency_voucher_can_be_reversed(): void
    {
        $afn = $this->ctx['currency'];
        $usd = Currency::query()->firstOrCreate(
            ['branch_id' => $this->branchId(), 'code' => 'USD'],
            [
                'name' => 'US Dollar',
                'symbol' => '$',
                'exchange_rate' => 60,
                'is_active' => true,
                'is_base_currency' => false,
                'flag' => 'us.png',
            ]
        );

        // A USD claim relieved by AFN cash: the dollars have no dollar
        // counterpart, so the voucher balances in base only.
        $original = $this->transactions->post(
            header: [
                'currency_id' => $afn->id,
                'rate' => 1,
                'date' => '2026-05-04',
                'remark' => 'cross currency',
                'cross_currency' => true,
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'currency_id' => $usd->id,
                    'rate' => 60,
                    'credit' => 100,
                ],
                [
                    'account_id' => $this->account('cash-in-hand'),
                    'currency_id' => $afn->id,
                    'rate' => 1,
                    'debit' => 6000,
                ],
            ],
        );

        $reversal = $this->transactions->reverse($original, 'undo', '2026-05-20');

        $this->assertTrue((bool) $reversal->is_cross_currency);
        $this->assertSame('reversed', $original->refresh()->status);
    }

    // ------------------------------------------------------
    // Endpoints
    // ------------------------------------------------------

    public function test_the_index_lists_years_with_their_periods(): void
    {
        $this->fiscalYears->ensureYearFor('2026-05-04', $this->branchId());

        $this->get(route('fiscal-years.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Accounting/FiscalYears/Index')
                ->has('fiscalYears', 1)
                ->has('fiscalYears.0.periods', 12));
    }

    public function test_closing_a_year_over_http_shuts_it(): void
    {
        $year = $this->fiscalYears->ensureYearFor('2026-05-04', $this->branchId());

        $this->patch(route('fiscal-years.close', $year))->assertRedirect();

        $this->assertSame(FinancialPeriodStatus::Closed, $year->refresh()->status);
    }

    /**
     * The permission gap found in the review: these routes carried no can:
     * middleware and authorizeResource does not map them, so anyone signed in
     * could close or reopen the books.
     */
    public function test_a_user_without_permission_cannot_close_a_year(): void
    {
        $year = $this->fiscalYears->ensureYearFor('2026-05-04', $this->branchId());

        $this->actingAs($this->ordinaryUser());

        $this->patch(route('fiscal-years.close', $year))->assertForbidden();

        $this->assertSame(FinancialPeriodStatus::Open, $year->refresh()->status);
    }

    public function test_generating_a_year_that_already_exists_is_refused(): void
    {
        $this->useCalendar(CalendarType::GREGORIAN, 1, 1);
        $this->fiscalYears->ensureYearFor('2026-05-04', $this->branchId());

        $this->post(route('fiscal-years.store'), ['date' => '2026-07-04'])
            ->assertSessionHasErrors('date');

        $this->assertSame(1, FiscalYear::withoutGlobalScopes()->count());
    }

    /**
     * Changing where the year starts after years exist would generate one that
     * straddles them, and the guard would then resolve a date to whichever row
     * it read first.
     */
    public function test_an_overlapping_year_cannot_be_generated(): void
    {
        $this->useCalendar(CalendarType::GREGORIAN, 1, 1);
        $this->fiscalYears->generate('2026-05-04', $this->branchId());

        $this->useCalendar(CalendarType::JALALI, 10, 1);

        $this->expectException(SettlementException::class);

        $this->fiscalYears->generate('2026-05-04', $this->branchId());
    }

    // ------------------------------------------------------
    // Reversal
    // ------------------------------------------------------

    /** A reversal lands where the books are open, not where the original sat. */
    public function test_a_reversal_can_be_dated_into_an_open_period(): void
    {
        $this->useCalendar(CalendarType::GREGORIAN, 1, 1);

        $original = $this->postOn('2026-05-04');
        $this->closePeriodCovering('2026-05-04');
        $this->actingAs($this->ordinaryUser());

        $reversal = $this->transactions->reverse($original->refresh(), 'undo', '2026-06-04');

        $this->assertSame('2026-06-04', $reversal->date->toDateString());
    }
}
