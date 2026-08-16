<?php

namespace Tests\Feature\Accounting;

use App\Exceptions\Accounting\InvalidPostingException;
use App\Models\Administration\Currency;
use App\Models\Transaction\Transaction;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Line-level multi-currency: posting invariants.
 *
 * These lock the four rules TransactionService enforces before it writes a
 * single row. Invariants 1 (per-currency balance) and 2 (base balance) are
 * both required — neither implies the other, and the FX settlement case is
 * exactly where they diverge.
 */
class LineLevelCurrencyPostingTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private TransactionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->service = app(TransactionService::class);
    }

    private function usd(float $rate = 60): Currency
    {
        return Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'exchange_rate' => $rate,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'us.png',
        ]);
    }

    private function header(array $overrides = []): array
    {
        return array_merge([
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'date' => '2026-08-14',
            'remark' => 'posting invariant test',
        ], $overrides);
    }

    public function test_an_afn_entry_balances_in_both_document_currency_and_base(): void
    {
        $transaction = $this->service->post($this->header(), [
            ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 500],
            ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'credit' => 500],
        ]);

        $lines = $transaction->lines()->get();

        $this->assertCount(2, $lines);
        $this->assertEquals(500, $lines->sum('debit'));
        $this->assertEquals(500, $lines->sum('credit'));
        $this->assertEquals(500, $lines->sum('base_debit'));
        $this->assertEquals(500, $lines->sum('base_credit'));

        // Every line inherits the header currency and rate when it omits them.
        foreach ($lines as $line) {
            $this->assertSame($this->ctx['currency']->id, $line->currency_id);
            $this->assertEquals(1, $line->rate);
        }

        $this->assertSame($this->ctx['currency']->id, $transaction->base_currency_id);
    }

    public function test_a_usd_entry_at_rate_60_stores_base_as_amount_times_rate(): void
    {
        $usd = $this->usd(60);

        $transaction = $this->service->post(
            $this->header(['currency_id' => $usd->id, 'rate' => 60]),
            [
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 100],
                ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'credit' => 100],
            ]
        );

        $lines = $transaction->lines()->get();

        $this->assertEquals(100, $lines->sum('debit'));
        $this->assertEquals(6000, $lines->sum('base_debit'));
        $this->assertEquals(6000, $lines->sum('base_credit'));

        foreach ($lines as $line) {
            $this->assertSame($usd->id, $line->currency_id);
            $this->assertEquals(60, $line->rate);
        }

        // The header still points at the branch's functional currency.
        $this->assertSame($this->ctx['currency']->id, $transaction->base_currency_id);
    }

    public function test_an_entry_unbalanced_in_document_currency_is_rejected(): void
    {
        $this->expectException(InvalidPostingException::class);
        $this->expectExceptionMessage('not balanced within every currency');

        $this->service->post($this->header(), [
            ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 500],
            ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'credit' => 400],
        ]);
    }

    public function test_an_entry_balanced_in_document_currency_but_not_in_base_is_rejected(): void
    {
        $usd = $this->usd(60);

        // Both USD lines balance at 100 = 100, but the credit side is valued at
        // a different rate, so the base totals diverge. This is the shape a
        // real FX settlement takes, and it must not post without an explicit
        // gain/loss line to absorb the difference.
        try {
            $this->service->post(
                $this->header(['currency_id' => $usd->id, 'rate' => 60]),
                [
                    [
                        'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                        'currency_id' => $usd->id,
                        'rate' => 60,
                        'debit' => 100,
                    ],
                    [
                        'account_id' => $this->ctx['accounts']['account-receivable']->id,
                        'currency_id' => $usd->id,
                        'rate' => 65,
                        'credit' => 100,
                    ],
                ]
            );

            $this->fail('Expected an entry that balances in USD but not in AFN to be rejected.');
        } catch (InvalidPostingException $e) {
            $this->assertStringContainsString('not balanced in base currency', $e->getMessage());
            // 6000 vs 6500 — the 500 AFN difference is the unposted FX result.
            $this->assertStringContainsString('-500', $e->getMessage());
        }

        $this->assertSame(0, Transaction::query()->count(), 'A rejected entry must not leave a header behind.');
        $this->assertSame(0, DB::table('transaction_lines')->count());
    }

    public function test_an_fx_line_may_leave_the_base_currency_one_sided(): void
    {
        $usd = $this->usd(60);

        // A customer owes $200 booked at 60 and pays $200 when the rate is 55.
        // The receivable is relieved at the rate it was BOOKED at, cash comes
        // in at today's rate, and the 1,000 AFN shortfall is a realised loss.
        //
        // USD balances: 200 debit, 200 credit. AFN does not, and cannot — the
        // loss has no dollar amount to pair with. Writing 0 USD on the FX line
        // to satisfy a naive per-currency check would be a lie about the line.
        $transaction = $this->service->post(
            $this->header(['currency_id' => $usd->id, 'rate' => 55]),
            [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'currency_id' => $usd->id,
                    'rate' => 55,
                    'debit' => 200,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'currency_id' => $usd->id,
                    'rate' => 60,
                    'credit' => 200,
                ],
                [
                    'account_id' => $this->ctx['accounts']['fx-loss']->id,
                    'currency_id' => $this->ctx['currency']->id,
                    'rate' => 1,
                    'debit' => 1000,
                ],
            ]
        );

        $lines = $transaction->lines()->get();

        $this->assertCount(3, $lines);
        $this->assertEquals(12000, $lines->sum('base_debit'));
        $this->assertEquals(12000, $lines->sum('base_credit'));

        $cash = $lines->firstWhere('account_id', $this->ctx['accounts']['cash-in-hand']->id);
        $this->assertEquals(200, $cash->debit);
        $this->assertEquals(55, $cash->rate);
        $this->assertEquals(11000, $cash->base_debit);

        $receivable = $lines->firstWhere('account_id', $this->ctx['accounts']['account-receivable']->id);
        $this->assertEquals(200, $receivable->credit);
        $this->assertEquals(60, $receivable->rate, 'A receivable is relieved at the rate it was booked at.');
        $this->assertEquals(12000, $receivable->base_credit);

        $loss = $lines->firstWhere('account_id', $this->ctx['accounts']['fx-loss']->id);
        $this->assertSame($this->ctx['currency']->id, $loss->currency_id, 'An FX line is AFN-only.');
        $this->assertEquals(1000, $loss->debit);
        $this->assertEquals(1000, $loss->base_debit);
        $this->assertEquals(1, $loss->rate);
    }

    public function test_a_cross_currency_entry_balancing_only_in_base_is_accepted(): void
    {
        $usd = $this->usd(60);

        // $100 of a @60 invoice settled with 5,500 AFN cash. Neither currency
        // self-balances here — the AFN cash line has no AFN counterpart at all
        // and the USD receivable has no USD counterpart. Only base ties out,
        // so the caller has to say out loud that this is what it meant.
        $transaction = $this->service->post(
            $this->header([
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'cross_currency' => true,
            ]),
            [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'currency_id' => $this->ctx['currency']->id,
                    'rate' => 1,
                    'debit' => 5500,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'currency_id' => $usd->id,
                    'rate' => 60,
                    'credit' => 100,
                ],
                [
                    'account_id' => $this->ctx['accounts']['fx-loss']->id,
                    'currency_id' => $this->ctx['currency']->id,
                    'rate' => 1,
                    'debit' => 500,
                ],
            ]
        );

        $lines = $transaction->lines()->get();

        $this->assertEquals(6000, $lines->sum('base_debit'));
        $this->assertEquals(6000, $lines->sum('base_credit'));
    }

    public function test_an_undeclared_cross_currency_entry_is_rejected(): void
    {
        $usd = $this->usd(60);

        // Same three lines as above, minus the declaration. Left implicit, a
        // USD credit with no USD debit is indistinguishable from a receivable
        // that was relieved by mistake, so it has to be refused.
        $this->expectException(InvalidPostingException::class);
        $this->expectExceptionMessage('not balanced within every currency');

        $this->service->post(
            $this->header(['currency_id' => $this->ctx['currency']->id, 'rate' => 1]),
            [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'currency_id' => $this->ctx['currency']->id,
                    'rate' => 1,
                    'debit' => 5500,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'currency_id' => $usd->id,
                    'rate' => 60,
                    'credit' => 100,
                ],
                [
                    'account_id' => $this->ctx['accounts']['fx-loss']->id,
                    'currency_id' => $this->ctx['currency']->id,
                    'rate' => 1,
                    'debit' => 500,
                ],
            ]
        );
    }

    public function test_the_cross_currency_flag_is_rejected_on_a_single_currency_entry(): void
    {
        $this->expectException(InvalidPostingException::class);
        $this->expectExceptionMessage('must touch more than one currency');

        $this->service->post(
            $this->header(['cross_currency' => true]),
            [
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 500],
                ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'credit' => 500],
            ]
        );
    }

    public function test_a_non_base_currency_must_still_self_balance(): void
    {
        $usd = $this->usd(60);

        // The exemption is for the base currency only. A USD side that does
        // not close is a broken subledger, not an FX difference, and no AFN
        // plug may paper over it — even though this entry ties in base.
        $this->expectException(InvalidPostingException::class);
        $this->expectExceptionMessage('not balanced within every currency');

        $this->service->post(
            $this->header(['currency_id' => $usd->id, 'rate' => 60]),
            [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'currency_id' => $usd->id,
                    'rate' => 60,
                    'debit' => 200,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'currency_id' => $usd->id,
                    'rate' => 60,
                    'credit' => 100,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'currency_id' => $this->ctx['currency']->id,
                    'rate' => 1,
                    'credit' => 6000,
                ],
            ]
        );
    }

    public function test_an_afn_only_entry_is_still_required_to_self_balance(): void
    {
        // The base-currency exemption exists to let AFN absorb a multi-currency
        // difference. A voucher that never leaves AFN has nothing to absorb, so
        // it stays under the per-currency rule and posts exactly as it did
        // before settlement existed.
        $this->expectException(InvalidPostingException::class);
        $this->expectExceptionMessage('not balanced within every currency');

        $this->service->post($this->header(), [
            [
                'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'debit' => 500,
            ],
            [
                'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'credit' => 400,
            ],
        ]);
    }

    public function test_a_line_with_both_debit_and_credit_is_rejected(): void
    {
        $this->expectException(InvalidPostingException::class);
        $this->expectExceptionMessage('either debit or credit');

        $this->service->post($this->header(), [
            ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 500, 'credit' => 200],
            ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'credit' => 300],
        ]);
    }

    public function test_a_line_whose_base_does_not_match_amount_times_rate_is_rejected(): void
    {
        $usd = $this->usd(60);

        $this->expectException(InvalidPostingException::class);
        $this->expectExceptionMessage('does not match amount x rate');

        $this->service->post(
            $this->header(['currency_id' => $usd->id, 'rate' => 60]),
            [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'debit' => 100,
                    // 100 x 60 = 6000, not 5000.
                    'base_debit' => 5000,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'credit' => 100,
                ],
            ]
        );
    }

    public function test_the_database_rejects_a_line_carrying_both_sides(): void
    {
        // Belt and braces: even a write that bypasses the service is stopped by
        // chk_single_side.
        $transaction = $this->service->post($this->header(), [
            ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 500],
            ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'credit' => 500],
        ]);

        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('transaction_lines')
            ->where('transaction_id', $transaction->id)
            ->limit(1)
            ->update(['debit' => 10, 'credit' => 10]);
    }

    public function test_a_reversal_keeps_each_line_at_its_original_rate(): void
    {
        $usd = $this->usd(60);

        $original = $this->service->post(
            $this->header(['currency_id' => $usd->id, 'rate' => 60]),
            [
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 100],
                ['account_id' => $this->ctx['accounts']['sales-revenue']->id, 'credit' => 100],
            ]
        );

        $reversal = $this->service->reverse($original->fresh('lines'), 'undo');

        $lines = $reversal->lines()->get();

        $this->assertEquals(100, $lines->sum('credit'));
        $this->assertEquals(6000, $lines->sum('base_credit'));
        $this->assertEquals(6000, $lines->sum('base_debit'));

        foreach ($lines as $line) {
            $this->assertEquals(60, $line->rate, 'A reversal must not re-value at today\'s rate.');
        }

        $this->assertSame('reversed', $original->fresh()->status);
    }
}
