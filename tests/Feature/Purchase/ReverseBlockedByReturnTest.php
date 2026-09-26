<?php

namespace Tests\Feature\Purchase;

use App\Enums\PurchaseReturnReason;
use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleReturn;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * A document that has been returned against cannot be reversed.
 *
 * A return takes its quantities and its costs from the document it is raised
 * against. Undoing that document underneath it leaves the return standing on
 * something that no longer happened: goods sent back that were never received,
 * and ledger lines referencing a voucher that has been undone. The return has
 * to be dealt with first.
 */
class ReverseBlockedByReturnTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    private function buy(float $quantity = 10, float $price = 20): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => random_int(4000, 4999),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => $quantity * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function returnPurchase(Purchase $purchase, float $quantity = 3): PurchaseReturn
    {
        $this->post(route('purchase-returns.store'), [
            'number' => random_int(4500, 4999),
            'purchase_id' => $purchase->id,
            'date' => now()->toDateString(),
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'damaged',
            'item_list' => [[
                'purchase_item_id' => $purchase->items->first()->id,
                'quantity' => $quantity,
            ]],
        ])->assertRedirect();

        return PurchaseReturn::query()->orderByDesc('id')->firstOrFail();
    }

    private function seedStock(float $quantity = 20, float $unitCost = 20): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => null, 'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null, 'reference_id' => null,
        ]);
    }

    private function sell(float $quantity = 10, float $price = 50): Sale
    {
        $this->post(route('sales.store'), [
            'number' => random_int(5000, 5999),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => $quantity * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function returnSale(Sale $sale, float $quantity = 4): SaleReturn
    {
        $this->post(route('sale-returns.store'), [
            'number' => random_int(5500, 5999),
            'sale_id' => $sale->id,
            'date' => now()->toDateString(),
            'reason' => SaleReturnReason::values()[0],
            'description' => 'customer brought them back',
            'item_list' => [[
                'sale_item_id' => $sale->items->first()->id,
                'quantity' => $quantity,
            ]],
        ])->assertRedirect();

        return SaleReturn::query()->orderByDesc('id')->firstOrFail();
    }

    // ------------------------------------------------------------ purchases

    public function test_a_purchase_with_a_return_against_it_cannot_be_reversed(): void
    {
        $purchase = $this->buy();
        $return = $this->returnPurchase($purchase);

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertSessionHasErrors('status');

        $this->assertSame(
            TransactionStatus::POSTED->value,
            $purchase->fresh()->status,
            'The purchase must be left exactly as it was.',
        );

        $this->assertSame(TransactionStatus::POSTED->value, $return->fresh()->status);
    }

    public function test_the_refusal_names_the_return_and_says_what_to_do(): void
    {
        $purchase = $this->buy();
        $return = $this->returnPurchase($purchase);

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled']);

        $message = implode(' ', session('errors')->all());

        $this->assertStringContainsString((string) $return->number, $message);
        $this->assertSame(
            __('general.cannot_reverse_document_with_returns', ['numbers' => $return->number]),
            $message,
        );
    }

    public function test_reversing_the_return_first_releases_the_purchase(): void
    {
        $purchase = $this->buy();
        $return = $this->returnPurchase($purchase);

        $this->post(route('purchase-returns.reverse', $return), ['reason' => 'raised by mistake'])
            ->assertRedirect();

        $this->assertSame(TransactionStatus::REVERSED->value, $return->fresh()->status);

        // The return no longer stands, so nothing is left pointing at the
        // purchase and it can be undone.
        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $purchase->fresh()->status);
    }

    /**
     * A draft return blocks too, and deliberately so.
     *
     * It has posted nothing yet, but it still points at this purchase's lines.
     * Reverse the purchase underneath it and the draft is left ready to post
     * against a document that no longer happened. The way out is to delete the
     * draft, which is one click and always allowed.
     */
    public function test_even_a_draft_return_blocks_the_purchase(): void
    {
        set_user_preference('transaction.purchase_return_post_immediately', false, $this->ctx['user']);

        $purchase = $this->buy();
        $return = $this->returnPurchase($purchase);

        $this->assertSame(
            TransactionStatus::DRAFT->value,
            $return->fresh()->status,
            'This test is only meaningful while the return is still a draft.',
        );

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TransactionStatus::POSTED->value, $purchase->fresh()->status);
    }

    public function test_deleting_the_draft_return_releases_the_purchase(): void
    {
        set_user_preference('transaction.purchase_return_post_immediately', false, $this->ctx['user']);

        $purchase = $this->buy();
        $return = $this->returnPurchase($purchase);

        $this->delete(route('purchase-returns.destroy', $return))->assertRedirect();

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $purchase->fresh()->status);
    }

    public function test_a_purchase_with_no_return_still_reverses(): void
    {
        $purchase = $this->buy();

        $this->post(route('purchases.reverse', $purchase), ['reason' => 'supplier cancelled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $purchase->fresh()->status);
    }

    // ---------------------------------------------------------------- sales

    public function test_a_sale_with_a_return_against_it_cannot_be_reversed(): void
    {
        $this->seedStock();
        $sale = $this->sell();
        $return = $this->returnSale($sale);

        $this->post(route('sales.reverse', $sale), ['reason' => 'customer cancelled'])
            ->assertSessionHasErrors('status');

        $this->assertSame(TransactionStatus::POSTED->value, $sale->fresh()->status);
        $this->assertSame(TransactionStatus::POSTED->value, $return->fresh()->status);
    }

    public function test_reversing_the_sale_return_first_releases_the_sale(): void
    {
        $this->seedStock();
        $sale = $this->sell();
        $return = $this->returnSale($sale);

        $this->post(route('sale-returns.reverse', $return), ['reason' => 'raised by mistake'])
            ->assertRedirect();

        $this->post(route('sales.reverse', $sale), ['reason' => 'customer cancelled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);
    }

    public function test_a_sale_with_no_return_still_reverses(): void
    {
        $this->seedStock();
        $sale = $this->sell();

        $this->post(route('sales.reverse', $sale), ['reason' => 'customer cancelled'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);
    }
}
