<?php

namespace Tests\Feature\Accounting;

use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Models\Transaction\Transaction;
use App\Services\Accounting\SaleReceiveBackfill;
use App\Services\TransactionService;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Migrating the legacy sale_receives allocations into settlements.
 *
 * The table is dropped by a later migration, so these tests recreate its shape
 * to have something to migrate FROM. That is deliberate — the backfill has to
 * keep working against the old schema right up to the moment it is dropped.
 */
class SaleReceiveBackfillTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->createLegacyTable();
    }

    private function createLegacyTable(): void
    {
        if (Schema::hasTable('sale_receives')) {
            return;
        }

        Schema::create('sale_receives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('sale_id');
            $table->ulid('receipt_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->ulid('created_by')->nullable();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('branch_id');
            $table->timestamps();
            $table->softDeletes();
        });
    }

    private function account(string $slug): string
    {
        return $this->ctx['accounts'][$slug]->id;
    }

    /**
     * A sale and its receivable line, posted the way SaleController posts one.
     */
    private function sale(float $amount, float $rate = 1): Sale
    {
        $sale = Sale::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-01-10',
        ]);

        app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => $rate,
                'date' => '2026-01-10',
                'reference_type' => Sale::class,
                'reference_id' => $sale->id,
                'remark' => 'sale',
            ],
            lines: [
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => $amount,
                ],
                ['account_id' => $this->account('sales-revenue'), 'credit' => $amount],
            ]
        );

        return $sale;
    }

    /**
     * A receipt posted the OLD way: a flat two-line voucher with no settlements.
     */
    private function legacyReceipt(float $amount, float $rate = 1): Receipt
    {
        $receipt = Receipt::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-02-01',
        ]);

        app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => $rate,
                'date' => '2026-02-01',
                'reference_type' => Receipt::class,
                'reference_id' => $receipt->id,
                'remark' => 'receipt',
            ],
            lines: [
                ['account_id' => $this->account('cash-in-hand'), 'debit' => $amount],
                [
                    'account_id' => $this->account('account-receivable'),
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'credit' => $amount,
                ],
            ]
        );

        return $receipt;
    }

    private function legacyAllocation(Sale $sale, Receipt $receipt, float $amount): string
    {
        $id = (string) Str::ulid();

        DB::table('sale_receives')->insert([
            'id' => $id,
            'sale_id' => $sale->id,
            'receipt_id' => $receipt->id,
            'amount' => $amount,
            'branch_id' => $this->ctx['branch']->id,
            'created_by' => $this->ctx['user']->id,
            'created_at' => '2026-02-01 08:30:00',
            'updated_at' => '2026-02-01 08:30:00',
        ]);

        return $id;
    }

    /** Total debits and credits across the whole ledger, in base currency. */
    private function trialBalance(): array
    {
        $row = DB::table('transaction_lines')
            ->selectRaw('COALESCE(SUM(base_debit), 0) as debit, COALESCE(SUM(base_credit), 0) as credit')
            ->first();

        return [(float) $row->debit, (float) $row->credit];
    }

    public function test_it_migrates_an_allocation_into_a_settlement(): void
    {
        $sale = $this->sale(500);
        $receipt = $this->legacyReceipt(500);
        $this->legacyAllocation($sale, $receipt, 500);

        $result = app(SaleReceiveBackfill::class)->run();

        $this->assertSame(1, $result['migrated']);
        $this->assertSame([], $result['skipped']);

        $settlement = DB::table('settlements')->first();

        $this->assertEquals(500, $settlement->amount_applied);
        $this->assertEquals(1, $settlement->target_rate);
        // No historical FX exists, so the cash is treated as having moved at
        // the rate the claim was booked at. Inventing a gain or loss from rates
        // nobody recorded would put numbers in the P&L that never happened.
        $this->assertEquals($settlement->target_rate, $settlement->settlement_rate);
        $this->assertEquals(0, $settlement->forex_amount);
        $this->assertEquals(500, $settlement->base_relieved);

        // The target is the SALE's receivable line, the settling line is the
        // RECEIPT's — not the documents themselves.
        $saleTransaction = Transaction::query()->where('reference_id', $sale->id)->firstOrFail();
        $receiptTransaction = Transaction::query()->where('reference_id', $receipt->id)->firstOrFail();

        $this->assertSame(
            $saleTransaction->lines->firstWhere('account_id', $this->account('account-receivable'))->id,
            $settlement->target_line_id
        );
        $this->assertSame(
            $receiptTransaction->lines->firstWhere('account_id', $this->account('account-receivable'))->id,
            $settlement->settling_line_id
        );
    }

    public function test_it_preserves_the_original_timestamps_and_author(): void
    {
        $sale = $this->sale(500);
        $receipt = $this->legacyReceipt(500);
        $this->legacyAllocation($sale, $receipt, 500);

        app(SaleReceiveBackfill::class)->run();

        $settlement = DB::table('settlements')->first();

        $this->assertStringStartsWith('2026-02-01', (string) $settlement->created_at);
        $this->assertSame($this->ctx['user']->id, $settlement->created_by);
    }

    public function test_running_it_twice_changes_nothing(): void
    {
        $sale = $this->sale(500);
        $receipt = $this->legacyReceipt(500);
        $this->legacyAllocation($sale, $receipt, 500);

        app(SaleReceiveBackfill::class)->run();
        $first = DB::table('settlements')->get();

        $second = app(SaleReceiveBackfill::class)->run();

        $this->assertSame(0, $second['migrated']);
        $this->assertSame(1, $second['already_present']);
        $this->assertEquals($first, DB::table('settlements')->get());
        $this->assertSame(1, DB::table('settlements')->count());
    }

    public function test_it_leaves_the_trial_balance_untouched(): void
    {
        $sale = $this->sale(500);
        $receipt = $this->legacyReceipt(500);
        $this->legacyAllocation($sale, $receipt, 500);

        $before = $this->trialBalance();

        app(SaleReceiveBackfill::class)->run();
        app(SaleReceiveBackfill::class)->run();

        // Settlement is a subledger record. It explains which journal lines
        // offset which — it never posts anything, so the general ledger cannot
        // move underneath it.
        $this->assertSame($before, $this->trialBalance());
        $this->assertEquals($before[0], $before[1]);
    }

    public function test_a_base_amount_is_converted_back_into_the_document_currency(): void
    {
        // The old column stored base currency; settlements store the target's
        // own currency, so a 300 AFN allocation against a $5 claim booked at 60
        // comes back as $5.
        $sale = $this->sale(5, 60);
        $receipt = $this->legacyReceipt(5, 60);
        $this->legacyAllocation($sale, $receipt, 300);

        app(SaleReceiveBackfill::class)->run();

        $settlement = DB::table('settlements')->first();

        $this->assertEquals(5, $settlement->amount_applied);
        $this->assertEquals(60, $settlement->target_rate);
        $this->assertEquals(300, $settlement->base_relieved);
    }

    public function test_an_unmatched_row_is_reported_rather_than_guessed_at(): void
    {
        $sale = $this->sale(500);
        $receipt = $this->legacyReceipt(500);

        // A receipt whose voucher was deleted has no receivable line to settle
        // from. Picking some other line would silently close the wrong invoice.
        $receiptTransaction = Transaction::query()->where('reference_id', $receipt->id)->firstOrFail();
        DB::table('transaction_lines')->where('transaction_id', $receiptTransaction->id)->delete();
        DB::table('transactions')->where('id', $receiptTransaction->id)->delete();

        $allocationId = $this->legacyAllocation($sale, $receipt, 500);

        $result = app(SaleReceiveBackfill::class)->run();

        $this->assertSame(0, $result['migrated']);
        $this->assertCount(1, $result['skipped']);
        $this->assertSame($allocationId, $result['skipped'][0]['sale_receive_id']);
        $this->assertStringContainsString('receipt has no receivable line', $result['skipped'][0]['reason']);
        $this->assertSame(0, DB::table('settlements')->count());
    }

    public function test_an_over_applied_row_is_reported_rather_than_written(): void
    {
        $sale = $this->sale(100);
        $receipt = $this->legacyReceipt(500);

        // 500 allocated against a 100 invoice. The old table had no constraint
        // stopping this; settlements does, and the row needs a human.
        $this->legacyAllocation($sale, $receipt, 500);

        $result = app(SaleReceiveBackfill::class)->run();

        $this->assertSame(0, $result['migrated']);
        $this->assertCount(1, $result['skipped']);
        $this->assertStringContainsString('exceeds what is open', $result['skipped'][0]['reason']);
    }

    public function test_the_migrated_allocation_closes_the_open_item(): void
    {
        $sale = $this->sale(500);
        $receipt = $this->legacyReceipt(500);
        $this->legacyAllocation($sale, $receipt, 500);

        $settlements = app(\App\Services\Accounting\SettlementService::class);

        $this->assertCount(1, $settlements->openItems($this->ctx['customer_ledger']->id));

        app(SaleReceiveBackfill::class)->run();

        // After the backfill the invoice reads as settled, which is the whole
        // point: history has to survive the move to the new table.
        $this->assertCount(0, $settlements->openItems($this->ctx['customer_ledger']->id));
    }
}
