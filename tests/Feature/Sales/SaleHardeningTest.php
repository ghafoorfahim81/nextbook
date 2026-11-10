<?php

namespace Tests\Feature\Sales;

use App\Enums\PaymentStatus;
use App\Enums\SaleOrderStatus;
use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleOrder;
use App\Models\Transaction\Transaction;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The rules a sale has to hold to whatever the form sends: what it comes to,
 * how it is paid, what its stock cost, and what a return gives back.
 */
class SaleHardeningTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function receive(float $quantity, float $unitCost, ?string $batch = null, ?string $expireDate = null, string $date = '2026-03-01'): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => $batch,
            'date' => $date,
            'expire_date' => $expireDate,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed-stock',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    private function line(float $quantity, float $price, array $overrides = []): array
    {
        return array_merge([
            'item_id' => $this->ctx['item']->id,
            'batch' => null,
            'expire_date' => null,
            'quantity' => $quantity,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'unit_price' => $price,
            'item_discount' => 0,
            'free' => 0,
            'tax' => 0,
        ], $overrides);
    }

    private function salePayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 7100,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 200,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [$this->line(5, 40)],
        ], $overrides);
    }

    private function customerBalance(): float
    {
        return (float) DB::table('transaction_lines')
            ->where('ledger_id', $this->ctx['customer_ledger']->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) as balance')
            ->value('balance');
    }

    private function saleLines(Sale $sale)
    {
        return DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.reference_type', Sale::class)
            ->where('transactions.reference_id', $sale->id);
    }

    public function test_a_cash_sale_has_to_name_the_account_the_money_goes_into(): void
    {
        $this->receive(20, 10);

        $this->post(route('sales.store'), $this->salePayload(['sale_type' => 'cash']))
            ->assertSessionHasErrors('bank_account_id');

        $this->assertDatabaseMissing('sales', ['number' => 7100]);
    }

    public function test_a_payment_cannot_be_more_than_the_bill(): void
    {
        $this->receive(20, 10);

        $this->post(route('sales.store'), $this->salePayload([
            'sale_type' => 'credit',
            'payment' => ['method' => 'cash', 'amount' => 250, 'account_id' => $this->ctx['accounts']['cash-in-hand']->id],
        ]))->assertSessionHasErrors('payment.amount');

        $this->assertDatabaseMissing('sales', ['number' => 7100]);
    }

    public function test_a_credit_sale_paid_in_full_is_paid(): void
    {
        $this->receive(20, 10);

        $this->post(route('sales.store'), $this->salePayload([
            'sale_type' => 'credit',
            'payment' => ['method' => 'cash', 'amount' => 200, 'account_id' => $this->ctx['accounts']['cash-in-hand']->id],
        ]))->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('number', 7100)->firstOrFail();

        $this->assertSame(PaymentStatus::Paid, $sale->payment_status);
        $this->assertEquals(0.0, $this->customerBalance());
    }

    public function test_a_credit_sale_paid_in_part_leaves_the_rest_owed(): void
    {
        $this->receive(20, 10);

        $this->post(route('sales.store'), $this->salePayload([
            'sale_type' => 'credit',
            'payment' => ['method' => 'cash', 'amount' => 50, 'account_id' => $this->ctx['accounts']['cash-in-hand']->id],
        ]))->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('number', 7100)->firstOrFail();

        $this->assertSame(PaymentStatus::PartiallyPaid, $sale->payment_status);
        $this->assertEquals(150.0, $this->customerBalance());
    }

    public function test_the_status_of_a_sale_cannot_be_set_directly(): void
    {
        $this->assertFalse(Route::has('sales.update-sale-status'));
    }

    public function test_the_bill_total_is_worked_out_from_the_lines_not_taken_from_the_form(): void
    {
        $this->receive(20, 10);

        // Two lines, a line discount and a fixed bill discount; the form's own
        // total is nonsense and must be ignored.
        $this->post(route('sales.store'), $this->salePayload([
            'transaction_total' => 99999,
            'discount_total' => 1,
            'discount' => 30,
            'discount_type' => 'currency',
            'item_list' => [
                $this->line(5, 40, ['item_discount' => 20]),
                $this->line(2, 50, ['batch' => null, 'unit_price' => 50, 'expire_date' => null, 'free' => 1]),
            ],
        ]))->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('number', 7100)->with('items')->firstOrFail();

        // 200 + 100 goods, less 20 on the line and 30 on the bill.
        $this->assertEquals(250.0, $this->customerBalance());
        // The fixed bill discount comes off once, not once per line.
        $this->assertEquals(250.0, $sale->saleTotal());
        $this->assertEquals(
            (float) $this->saleLines($sale)->sum('debit'),
            (float) $this->saleLines($sale)->sum('credit'),
        );
    }

    public function test_two_lines_of_one_item_are_each_costed_from_the_layers_they_take(): void
    {
        // FIFO: ten at 12, then seven at 14.
        $this->receive(10, 12, date: '2026-03-01');
        $this->receive(7, 14, date: '2026-03-02');

        $this->post(route('sales.store'), $this->salePayload([
            'item_list' => [
                $this->line(10, 40),
                $this->line(5, 40, ['unit_price' => 41]),
            ],
        ]))->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('number', 7100)->with('items')->firstOrFail();

        $issued = (float) StockMovement::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('movement_type', StockMovementType::OUT->value)
            ->get()
            ->sum(fn ($movement) => (float) $movement->quantity * (float) $movement->unit_cost);

        // 10 x 12 + 5 x 14: the second line must not be priced off the first layer again.
        $this->assertEquals(190.0, $issued);
        $this->assertEquals(
            190.0,
            (float) $this->saleLines($sale)->where('transaction_lines.account_id', $this->ctx['item']->asset_account_id)->sum('credit'),
        );
        $this->assertEqualsCanonicalizing(
            [12.0, 14.0],
            $sale->items->map(fn ($item) => (float) $item->net_unit_cost)->all(),
        );
    }

    public function test_a_draft_is_costed_on_the_day_it_is_posted(): void
    {
        set_company_setting('transaction.sale_post_immediately', false);
        $this->receive(10, 10, date: '2026-03-01');

        $this->post(route('sales.store'), $this->salePayload([
            'sale_type' => 'cash',
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'item_list' => [$this->line(12, 40)],
        ]))->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('number', 7100)->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $sale->status);

        // The edit form reads the chosen account back from the draft.
        $resource = (new \App\Http\Resources\Sale\SaleResource($sale->load(['items', 'transaction'])))->resolve(request());
        $this->assertSame($this->ctx['accounts']['cash-in-hand']->id, $resource['bank_account_id']);

        // More stock arrives, dearer, before the draft is posted.
        $this->receive(10, 20, date: '2026-03-05');

        $this->post(route('sales.post', $sale))->assertRedirect();

        $sale->refresh();
        $this->assertSame(TransactionStatus::POSTED->value, $sale->status);

        // FIFO: ten at 10 and two at 20 = 140, not the 120 the draft guessed.
        $this->assertEquals(
            140.0,
            (float) $this->saleLines($sale)->where('transaction_lines.account_id', $this->ctx['item']->asset_account_id)->sum('credit'),
        );
        $this->assertEquals(
            (float) $this->saleLines($sale)->sum('debit'),
            (float) $this->saleLines($sale)->sum('credit'),
        );
    }

    public function test_deleting_and_restoring_a_draft_only_moves_its_reservation(): void
    {
        set_company_setting('transaction.sale_post_immediately', false);
        $this->receive(20, 10);

        $this->post(route('sales.store'), $this->salePayload())->assertRedirect(route('sales.index'));
        $sale = Sale::query()->where('number', 7100)->firstOrFail();

        $balance = fn () => StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->get();

        $this->assertEquals(5.0, (float) $balance()->sum('reserved_out'));

        $this->delete(route('sales.destroy', $sale))->assertRedirect(route('sales.index'));

        $this->assertEquals(0.0, (float) $balance()->sum('reserved_out'));
        $this->assertEquals(20.0, (float) $balance()->sum('quantity'));
        // The draft voucher goes to the bin with its sale.
        $this->assertNull(Transaction::query()->where('reference_id', $sale->id)->first());

        $this->patch(route('sales.restore', $sale->id))->assertRedirect(route('sales.index'));

        $this->assertEquals(5.0, (float) $balance()->sum('reserved_out'));
        $this->assertEquals(20.0, (float) $balance()->sum('quantity'));
        $this->assertNotNull(Transaction::query()->where('reference_id', $sale->id)->first());

        // And it can still be posted afterwards.
        $this->post(route('sales.post', $sale->id))->assertRedirect();
        $this->assertEquals(15.0, (float) $balance()->sum('quantity'));
        $this->assertEquals(0.0, (float) $balance()->sum('reserved_out'));
    }

    public function test_a_return_gives_back_what_was_paid_and_free_units_can_come_back(): void
    {
        $this->receive(250, 10);

        // 45 sold + 5 free at 40, 100 off the line and 10% off the bill:
        // 1800 - 100 - 180 = 1520 for 50 units, 30.40 each.
        $this->post(route('sales.store'), $this->salePayload([
            'discount' => 10,
            'discount_type' => 'percentage',
            'item_list' => [$this->line(45, 40, ['item_discount' => 100, 'free' => 5])],
        ]))->assertRedirect(route('sales.index'));

        $sale = Sale::query()->where('number', 7100)->with('items')->firstOrFail();
        $this->assertEquals(1520.0, $this->customerBalance());

        $this->getJson(route('sale-returns.returnable-items', ['sale_id' => $sale->id]))
            ->assertOk()
            ->assertJsonPath('items.0.remaining_quantity', 50)
            ->assertJsonPath('items.0.unit_price', 30.4);

        $return = fn (int $number, float $quantity) => $this->post(route('sale-returns.store'), [
            'number' => $number,
            'sale_id' => $sale->id,
            'date' => '2026-03-20',
            'reason' => SaleReturnReason::values()[0],
            'item_list' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => $quantity]],
        ]);

        $return(8601, 10)->assertRedirect();

        // Ten units at 30.40, not ten at the list price of 40.
        $this->assertEquals(1216.0, $this->customerBalance());

        // The rest, free units included, can come back too, and leaves nothing owed.
        $return(8602, 40)->assertRedirect();
        $this->assertEquals(0.0, round($this->customerBalance(), 2));

        // But not a unit more than was handed over.
        $return(8603, 1)->assertSessionHasErrors('item_list');

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'quantity' => 250.0000,
        ]);
    }

    public function test_reversing_the_sale_reopens_its_order_and_an_order_is_billed_once(): void
    {
        $this->receive(20, 10);

        $this->post(route('sale-orders.store'), [
            'number' => 5001,
            'date' => '2026-03-19',
            'customer_id' => $this->ctx['customer_ledger']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'discount' => 10,
            'discount_type' => 'percentage',
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'quantity' => 5,
                'free' => 0,
                'unit_price' => 40,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'discount' => 20,
            ]],
        ])->assertRedirect(route('sale-orders.index'));

        $order = SaleOrder::query()->with('items')->latest()->firstOrFail();

        // 200 goods, 20 off the line, 10% of the goods off the bill: the sum
        // the sale made from it will post.
        $this->assertEquals(160.0, $order->orderTotal());

        $this->post(route('sales.store'), $this->salePayload(['sale_order_id' => $order->id]))
            ->assertRedirect(route('sales.index'));
        $sale = Sale::query()->where('number', 7100)->firstOrFail();
        $this->assertSame(SaleOrderStatus::COMPLETED->value, $order->fresh()->status);

        $this->post(route('sales.reverse', $sale), ['reason' => 'entered twice'])->assertRedirect();
        $this->assertSame(SaleOrderStatus::POSTED->value, $order->fresh()->status);

        // Billed again as a draft; a second bill from the same order is refused.
        set_company_setting('transaction.sale_post_immediately', false);
        $this->post(route('sales.store'), $this->salePayload(['number' => 7101, 'sale_order_id' => $order->id]))
            ->assertRedirect(route('sales.index'));
        $this->post(route('sales.store'), $this->salePayload(['number' => 7102, 'sale_order_id' => $order->id]))
            ->assertSessionHasErrors('sale_order_id');
    }
}
