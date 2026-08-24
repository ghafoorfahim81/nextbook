<?php

namespace Tests\Feature\Sales;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Administration\Currency;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/** The sale list names the currency each sale was billed in. */
class SaleListCurrencyTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_sale_list_carries_the_transaction_currency_code(): void
    {
        $usd = Currency::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'US Dollar',
            'code' => 'USD',
            'symbol' => '$',
            'exchange_rate' => 70,
            'is_active' => true,
            'is_base_currency' => false,
            'flag' => 'us.png',
        ]);

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 15,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 12,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed-sale-stock',
            'reference_id' => $this->ctx['item']->id,
        ]);

        $this->post(route('sales.store'), [
            'number' => 7101,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 200,
            'currency_id' => $usd->id,
            'rate' => 70,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'sale list currency test',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => null,
                    'expire_date' => null,
                    'quantity' => 5,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 40,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ])->assertRedirect(route('sales.index'));

        $this->get(route('sales.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Sale/Sales/Index')
                ->where('sales.data.0.currency_code', 'USD'));
    }
}
