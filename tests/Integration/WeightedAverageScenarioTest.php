<?php

namespace Tests\Integration;

use App\Enums\CostingMethod;
use App\Enums\PurchaseReturnReason;
use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Models\Account\Account;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseReturn;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleReturn;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The published weighted-average scenario, walked end to end.
 *
 * Weighted average is the other half of the costing engine and behaves
 * differently at almost every step: an issue leaves at today's blended cost
 * rather than at the oldest layer's, one OUT row covers a whole line, and
 * quantity x average really does equal the inventory account — which is the
 * check FIFO cannot be held to.
 */
class WeightedAverageScenarioTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Item $item;

    private ItemVariant $gradeOne;

    private ItemVariant $gradeTwo;

    private UnitMeasure $tank;

    private Warehouse $second;

    private array $log = [];

    private array $findings = [];

    private int $docNumber = 6000;

    private string $today;

    private Purchase $purchase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        $this->today = now()->toDateString();

        foreach ([
            ['Inventory Shrinkage & Wastage', '9040', 'inventory-shrinkage-and-wastage'],
            ['Inventory Adjustments', '9050', 'inventory-adjustments'],
        ] as [$name, $number, $slug]) {
            Account::factory()->create([
                'branch_id' => $this->ctx['branch']->id,
                'name' => $name,
                'number' => $number,
                'slug' => $slug,
                'account_type_id' => $this->ctx['account_types']['expense']->id,
                'is_main' => true,
                'is_active' => true,
            ]);
        }

        $this->second = Warehouse::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Overflow Warehouse',
        ]);

        // One tank is twenty litres.
        $this->tank = UnitMeasure::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'quantity_id' => $this->ctx['quantity']->id,
            'name' => 'Tank',
            'unit' => '20',
            'symbol' => 'tk',
            'is_main' => false,
            'is_active' => true,
        ]);
    }

    // ---------------------------------------------------------------- probes

    private function onHand(?string $warehouseId = null, ?string $variantId = null): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->item->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($variantId, fn ($q) => $q->where('variant_id', $variantId))
            ->sum('quantity');
    }

    private function gl(string $slug): float
    {
        return (float) DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transaction_lines.account_id', $this->ctx['accounts'][$slug]->id ?? null)
            ->whereIn('transactions.status', ['posted', 'reversed'])
            ->whereNull('transaction_lines.deleted_at')
            ->selectRaw('COALESCE(SUM(transaction_lines.debit - transaction_lines.credit), 0) AS b')
            ->value('b');
    }

    private function avg(?ItemVariant $variant = null): float
    {
        return $variant
            ? (float) $variant->fresh()->avg_cost
            : (float) $this->item->fresh()->avg_cost;
    }

    private function flag(string $step, string $issue): void
    {
        $this->findings[] = $step . ' :: ' . $issue;
    }

    private function near(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }

    private function expect(string $step, string $label, float $actual, float $published): void
    {
        if (! $this->near($actual, $published)) {
            $this->flag($step, sprintf('%s is %.4f, the scenario says %.4f', $label, $actual, $published));
        }
    }

    /**
     * Under weighted average the two sides must agree exactly: what the shelf
     * holds, valued at the blended cost, is what the inventory account says.
     */
    private function snapshot(string $step): void
    {
        $gradeOne = $this->onHand(null, $this->gradeOne->id) * $this->avg($this->gradeOne);
        $gradeTwo = $this->onHand(null, $this->gradeTwo->id) * $this->avg($this->gradeTwo);
        $gl = $this->gl('inventory-stock');

        $this->log[] = sprintf(
            '%-40s g1=%7.2f @ %8.4f  g2=%6.2f @ %8.4f  value=%10.2f  GL=%10.2f  item=%8.4f',
            $step,
            $this->onHand(null, $this->gradeOne->id),
            $this->avg($this->gradeOne),
            $this->onHand(null, $this->gradeTwo->id),
            $this->avg($this->gradeTwo),
            $gradeOne + $gradeTwo,
            $gl,
            $this->avg(),
        );

        if (! $this->near($gradeOne + $gradeTwo, $gl)) {
            $this->flag($step, sprintf(
                'quantity x average is %.2f but the inventory GL holds %.2f',
                $gradeOne + $gradeTwo,
                $gl,
            ));
        }
    }

    private function act(string $step, string $url, array $payload = []): bool
    {
        $this->post($url, $payload);
        $ok = true;

        if (($errors = session('errors')) && count($errors->all()) > 0) {
            $this->flag($step, 'rejected: ' . implode(' | ', $errors->all()));
            session()->forget('errors');
            $ok = false;
        }

        if (session('error')) {
            $this->flag($step, 'rejected: ' . session('error'));
            session()->forget('error');
            $ok = false;
        }

        return $ok;
    }

    private function n(): int
    {
        return ++$this->docNumber;
    }

    // ------------------------------------------------------------- documents

    private function buy(float $qty, float $price, ?UnitMeasure $unit = null): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => $this->n(),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => $this->today,
            'transaction_total' => $qty * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->item->id,
                'variant_id' => $this->gradeOne->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $qty,
                'unit_measure_id' => ($unit ?? $this->ctx['unit_measure'])->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function sell(float $qty, float $price = 150): Sale
    {
        $this->post(route('sales.store'), [
            'number' => $this->n(),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => $this->today,
            'transaction_total' => $qty * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->item->id,
                'variant_id' => $this->gradeOne->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $qty,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function cogsOf(Sale $sale): float
    {
        $transactionId = DB::table('transactions')
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->whereNull('reversal_of_id')
            ->value('id');

        return (float) DB::table('transaction_lines')
            ->where('transaction_id', $transactionId)
            ->where('account_id', $this->ctx['accounts']['cost-of-goods-sold']->id)
            ->sum('debit');
    }

    private function adjust(float $qty, string $reason, ?float $unitCost = null): StockAdjustment
    {
        $line = [
            'item_id' => $this->item->id,
            'variant_id' => $this->gradeOne->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $qty,
        ];

        if ($unitCost !== null) {
            $line['unit_cost'] = $unitCost;
        }

        $this->post(route('stock-adjustments.store'), [
            'date' => $this->today,
            'reason' => $reason,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'notes' => 'wac scenario',
            'items' => [$line],
        ])->assertRedirect(route('stock-adjustments.index'));

        return StockAdjustment::query()->orderByDesc('id')->firstOrFail();
    }

    // ----------------------------------------------------------------- walk

    public function test_walk_the_weighted_average_scenario(): void
    {
        $this->phase01();
        $this->phase02();
        $this->phase03();
        $this->phase04();
        $this->phase05();
        $this->phase06();
        $this->phase07();

        if ($this->findings === []) {
            $this->assertSame([], $this->findings);

            return;
        }

        // Only on a failure: the whole walk, so a broken step reads in context.
        fwrite(STDERR, "

============ WEIGHTED AVERAGE WALKTHROUGH ============
");
        fwrite(STDERR, implode("
", $this->log) . "
");
        fwrite(STDERR, "======================================================

");

        $this->fail(
            count($this->findings) . ' step(s) did not match the published scenario:' . PHP_EOL
            . implode(PHP_EOL, $this->findings)
        );
    }

    private function phase01(): void
    {
        $this->post(route('items.store'), [
            'name' => 'Olive Oil',
            'code' => 'OO-200',
            'item_type' => 'inventory_materials',
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'costing_method' => CostingMethod::WEIGHTED_AVERAGE->value,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['product-income']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'sale_price' => 150,
            'purchase_price' => 50,
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
            'variants' => [
                ['attributes' => ['grade' => 'one'], 'sku' => 'OO-G1', 'purchase_price' => 50, 'is_default' => true, 'sort_order' => 0],
                ['attributes' => ['grade' => 'two'], 'sku' => 'OO-G2', 'purchase_price' => 80, 'sort_order' => 1],
            ],
            'openings' => [
                ['quantity' => 100, 'unit_price' => 50, 'warehouse_id' => $this->ctx['warehouse']->id, 'batch' => null, 'expire_date' => null, 'variant_index' => 0],
                ['quantity' => 50, 'unit_price' => 80, 'warehouse_id' => $this->ctx['warehouse']->id, 'batch' => null, 'expire_date' => null, 'variant_index' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->item = Item::where('code', 'OO-200')->firstOrFail();
        $bySku = $this->item->variants->keyBy('sku');
        $this->gradeOne = $bySku['OO-G1'];
        $this->gradeTwo = $bySku['OO-G2'];

        Cache::put('costing_method', CostingMethod::WEIGHTED_AVERAGE->value);

        $step = 'P01 opening 100@50 + 50@80';
        $this->expect($step, 'costing method', $this->item->effectiveCostingMethod() === CostingMethod::WEIGHTED_AVERAGE ? 1 : 0, 1);
        $this->expect($step, 'total on hand', $this->onHand(), 150);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 9000);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 50);
        $this->expect($step, 'grade two average', $this->avg($this->gradeTwo), 80);
        $this->expect($step, 'item average', $this->avg(), 60);
        $this->snapshot($step);
    }

    private function phase02(): void
    {
        $this->purchase = $this->buy(5, 1400, $this->tank);

        $step = 'P02 purchase 5 tanks @ 1400';
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 200);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 60);
        $this->expect($step, 'item average', $this->avg(), 64);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 16000);
        $this->snapshot($step);
    }

    private function phase03(): void
    {
        $sale = $this->sell(50);

        $step = 'P03a sale 50 at the blended cost';
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 150);
        $this->expect($step, 'COGS', $this->cogsOf($sale), 3000);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 13000);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 60);

        $rows = StockMovement::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('movement_type', StockMovementType::OUT->value)
            ->count();

        if ($rows !== 1) {
            $this->flag($step, 'the sale produced ' . $rows . ' OUT rows, weighted average should make one');
        }

        $this->snapshot($step);

        $this->post(route('sale-returns.store'), [
            'number' => $this->n(),
            'sale_id' => $sale->id,
            'date' => $this->today,
            'reason' => SaleReturnReason::values()[0],
            'description' => 'wac scenario',
            'item_list' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 20]],
        ])->assertRedirect();

        $saleReturn = SaleReturn::query()->orderByDesc('id')->firstOrFail();

        $step = 'P03b sale return 20';
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 170);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 14200);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 60);
        $this->snapshot($step);

        $step = 'P03c reverse both';
        $this->act($step, route('sale-returns.reverse', $saleReturn), ['reason' => 'wac scenario']);
        $this->act($step, route('sales.reverse', $sale), ['reason' => 'wac scenario']);
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 200);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 16000);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 60);
        $this->snapshot($step);
    }

    private function phase04(): void
    {
        $this->post(route('purchase-returns.store'), [
            'number' => $this->n(),
            'purchase_id' => $this->purchase->id,
            'date' => $this->today,
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'wac scenario',
            'item_list' => [['purchase_item_id' => $this->purchase->items->first()->id, 'quantity' => 1]],
        ])->assertRedirect();

        $return = PurchaseReturn::query()->orderByDesc('id')->firstOrFail();

        $step = 'P04a purchase return 1 tank';
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 180);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 14600);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 58.8889);
        $this->snapshot($step);

        $step = 'P04b reverse the purchase return';
        $this->act($step, route('purchase-returns.reverse', $return), ['reason' => 'wac scenario']);
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 200);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 16000);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 60);
        $this->snapshot($step);
    }

    private function phase05(): void
    {
        $out = $this->adjust(10, 'damage');

        $step = 'P05a adjustment OUT 10';
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 190);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 15400);
        $this->snapshot($step);

        $step = 'P05b reverse it';
        $this->act($step, route('stock-adjustments.reverse', $out), ['reason' => 'wac scenario']);
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 200);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 16000);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 60);
        $this->snapshot($step);

        $this->adjust(50, 'found', 80);

        $step = 'P05c adjustment IN 50 @ 80';
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 250);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 64);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 20000);
        $this->snapshot($step);
    }

    private function phase06(): void
    {
        set_user_preference('transaction.item_transfer_post_immediately', true, $this->ctx['user']);

        $this->post(route('item-transfers.store'), [
            'date' => $this->today,
            'from_warehouse_id' => $this->ctx['warehouse']->id,
            'to_warehouse_id' => $this->second->id,
            'remarks' => null,
            'has_transfer_cost' => true,
            'transfer_cost' => 300,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'items' => [[
                'item_id' => $this->item->id,
                'variant_id' => $this->gradeOne->id,
                'quantity' => 100,
                'measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 64,
            ]],
        ])->assertRedirect(route('item-transfers.index'));

        $transfer = ItemTransfer::query()->orderByDesc('id')->firstOrFail();

        $step = 'P06a transfer 100 out';
        $this->expect($step, 'main warehouse', $this->onHand($this->ctx['warehouse']->id, $this->gradeOne->id), 150);
        $this->expect($step, 'second warehouse', $this->onHand($this->second->id, $this->gradeOne->id), 100);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 64);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 20000);
        $this->expect($step, 'transfer expense', $this->gl('item-transfer-expense'), 300);
        $this->snapshot($step);

        $step = 'P06b reverse the transfer';
        $this->act($step, route('item-transfers.reverse', $transfer), ['reason' => 'wac scenario']);
        $this->expect($step, 'main warehouse', $this->onHand($this->ctx['warehouse']->id, $this->gradeOne->id), 250);
        $this->expect($step, 'second warehouse', $this->onHand($this->second->id, $this->gradeOne->id), 0);
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 64);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 20000);
        $this->expect($step, 'transfer expense', $this->gl('item-transfer-expense'), 0);
        $this->snapshot($step);
    }

    private function phase07(): void
    {
        $sale = $this->sell(250);

        $step = 'P07a sell everything';
        $this->expect($step, 'grade one on hand', $this->onHand(null, $this->gradeOne->id), 0);
        $this->expect($step, 'COGS', $this->cogsOf($sale), 16000);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 4000);
        $this->snapshot($step);

        $this->buy(50, 120);

        $step = 'P07b restock 50 @ 120 on an empty shelf';
        $this->expect($step, 'grade one average', $this->avg($this->gradeOne), 120);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 10000);
        $this->snapshot($step);
    }
}
