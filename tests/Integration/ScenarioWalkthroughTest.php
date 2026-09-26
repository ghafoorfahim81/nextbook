<?php

namespace Tests\Integration;

use App\Enums\PurchaseReturnReason;
use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
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
 * Replays the published manual test scenario and checks every figure it
 * promises, so the document stays true as the code moves.
 *
 * It earns its place by being long: the defects it was written for only appear
 * once a document has been reversed and its movements voided, and a later
 * document has to be costed around them. A short test never builds that state.
 */
class ScenarioWalkthroughTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Item $item;

    private ItemVariant $red;

    private ItemVariant $blue;

    private UnitMeasure $carton;

    private Warehouse $second;

    private array $log = [];

    private array $findings = [];

    private int $docNumber = 5000;

    private string $today;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        Cache::put('costing_method', 'fifo');
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

        // Phase 00
        $this->second = Warehouse::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Overflow Warehouse',
        ]);

        $this->carton = UnitMeasure::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'quantity_id' => $this->ctx['quantity']->id,
            'name' => 'Carton',
            'unit' => '12',
            'symbol' => 'ctn',
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

    private function layers(?string $warehouseId = null): array
    {
        $rows = StockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('movement_type', StockMovementType::IN->value)
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->where('qty_remaining', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get(['unit_measure_id', 'qty_remaining', 'unit_cost']);

        $units = UnitMeasure::withTrashed()
            ->whereIn('id', $rows->pluck('unit_measure_id')->unique())
            ->pluck('unit', 'id');

        $itemUnit = (float) UnitMeasure::withTrashed()
            ->whereKey($this->item->unit_measure_id)->value('unit') ?: 1.0;

        $value = 0.0;
        foreach ($rows as $row) {
            $factor = ((float) ($units[$row->unit_measure_id] ?? $itemUnit) / $itemUnit) ?: 1.0;
            $value += (float) $row->qty_remaining * ((float) $row->unit_cost / $factor);
        }

        return ['qty' => (float) $rows->sum('qty_remaining'), 'value' => $value];
    }

    private function avg(?ItemVariant $variant = null): float
    {
        return $variant
            ? (float) $variant->fresh()->avg_cost
            : (float) $this->item->fresh()->avg_cost;
    }

    /**
     * The two stock values the item page shows: the one in the header, and the
     * sum of the per-warehouse rows underneath it. They are separate
     * calculations, and the operator sees both at once.
     *
     * @return array{header: float, rows: float}
     */
    private function pageStockValues(): array
    {
        $page = $this->get(route('items.show', $this->item))->assertOk();
        $props = $page->viewData('page')['props'];

        // ItemResource wraps itself in a `data` key.
        $header = (float) str_replace(',', '', (string) $props['item']['data']['stock_value']);
        $rows = 0.0;

        foreach ($props['stockByWarehouse'] as $row) {
            $rows += (float) $row['stock_value'];
        }

        return ['header' => $header, 'rows' => $rows];
    }

    private function flag(string $step, string $issue): void
    {
        $this->findings[] = $step . ' :: ' . $issue;
    }

    private function near(float $a, float $b): bool
    {
        return abs($a - $b) < 0.01;
    }

    /** Compare one published figure against reality. */
    private function expect(string $step, string $label, float $actual, float $published): void
    {
        if (! $this->near($actual, $published)) {
            $this->flag($step, sprintf('%s is %.4f, the scenario says %.4f', $label, $actual, $published));
        }
    }

    private function snapshot(string $step): void
    {
        $layers = $this->layers();
        $this->log[] = sprintf(
            '%-38s red=%7.2f all=%7.2f GL=%10.2f layers=%7.2f/%10.2f avgRed=%9.4f avgItem=%9.4f',
            $step,
            $this->onHand(null, $this->red->id),
            $this->onHand(),
            $this->gl('inventory-stock'),
            $layers['qty'],
            $layers['value'],
            $this->avg($this->red),
            $this->avg(),
        );

        // The one invariant that must hold at every single step.
        if (! $this->near($layers['qty'], $this->onHand())) {
            $this->flag($step, sprintf('layers hold %.2f but the shelf has %.2f', $layers['qty'], $this->onHand()));
        }

        if (! $this->near($layers['value'], $this->gl('inventory-stock'))) {
            $this->flag($step, sprintf(
                'layers are worth %.2f but the inventory GL holds %.2f',
                $layers['value'],
                $this->gl('inventory-stock'),
            ));
        }

        // What the operator actually reads on the item page.
        $page = $this->pageStockValues();

        if (! $this->near($page['header'], $this->gl('inventory-stock'))) {
            $this->flag($step, sprintf(
                'the item page shows a stock value of %.2f against an inventory GL of %.2f',
                $page['header'],
                $this->gl('inventory-stock'),
            ));
        }

        if (! $this->near($page['rows'], $page['header'])) {
            $this->flag($step, sprintf(
                'the warehouse rows add up to %.2f but the header above them says %.2f',
                $page['rows'],
                $page['header'],
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
                'variant_id' => $this->red->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $qty,
                'unit_measure_id' => ($unit ?? $this->ctx['unit_measure'])->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function sell(float $qty, float $price = 90): Sale
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
                'variant_id' => $this->red->id,
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
            'variant_id' => $this->red->id,
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
            'notes' => 'scenario',
            'items' => [$line],
        ])->assertRedirect(route('stock-adjustments.index'));

        return StockAdjustment::query()->orderByDesc('id')->firstOrFail();
    }

    // ----------------------------------------------------------------- walk

    public function test_walk_the_published_scenario(): void
    {
        $this->phase01();
        $this->phase02();
        $this->phase03();
        $this->phase04();
        $this->phase05();
        $this->phase06();
        $this->phase07();
        $this->phase08();
        $this->phase09();

        if ($this->findings === []) {
            $this->assertSame([], $this->findings);

            return;
        }

        // Only on a failure: the whole walk, so the step that broke can be
        // read against the ones around it.
        fwrite(STDERR, "\n\n=============== SCENARIO WALKTHROUGH ===============\n");
        fwrite(STDERR, implode("\n", $this->log) . "\n");
        fwrite(STDERR, "====================================================\n\n");

        $this->fail(
            count($this->findings) . ' step(s) did not match the published scenario:' . PHP_EOL
            . implode(PHP_EOL, $this->findings)
        );
    }

    private function phase01(): void
    {
        $this->post(route('items.store'), [
            'name' => 'Magic Lamp',
            'code' => 'CJ-100',
            'item_type' => 'inventory_materials',
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['product-income']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'sale_price' => 90,
            'purchase_price' => 50,
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
            'variants' => [
                ['attributes' => ['color' => 'red'], 'sku' => 'CJ-RED', 'purchase_price' => 50, 'is_default' => true, 'sort_order' => 0],
                ['attributes' => ['color' => 'blue'], 'sku' => 'CJ-BLUE', 'purchase_price' => 80, 'sort_order' => 1],
            ],
            'openings' => [
                ['quantity' => 100, 'unit_price' => 50, 'warehouse_id' => $this->ctx['warehouse']->id, 'batch' => null, 'expire_date' => null, 'variant_index' => 0],
                ['quantity' => 50, 'unit_price' => 80, 'warehouse_id' => $this->ctx['warehouse']->id, 'batch' => null, 'expire_date' => null, 'variant_index' => 1],
            ],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->item = Item::where('code', 'CJ-100')->firstOrFail();
        $bySku = $this->item->variants->keyBy('sku');
        $this->red = $bySku['CJ-RED'];
        $this->blue = $bySku['CJ-BLUE'];

        $step = 'P01 opening 100@50 + 50@80';

        foreach ($this->item->variants as $variant) {
            if (! $variant->is_active) {
                $this->flag($step, 'variant ' . $variant->sku . ' was created inactive');
            }
        }

        $this->expect($step, 'total on hand', $this->onHand(), 150);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 9000);
        $this->expect($step, 'red average', $this->avg($this->red), 50);
        $this->expect($step, 'blue average', $this->avg($this->blue), 80);
        $this->expect($step, 'item average', $this->avg(), 60);
        $this->snapshot($step);
    }

    private function phase02(): void
    {
        $this->purchase = $this->buy(5, 600, $this->carton);

        $step = 'P02 purchase 5 cartons @ 600';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 160);
        $this->expect($step, 'total on hand', $this->onHand(), 210);
        $this->expect($step, 'red average', $this->avg($this->red), 50);
        $this->expect($step, 'item average', $this->avg(), 57.1429);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12000);
        $this->snapshot($step);
    }

    private Purchase $purchase;

    private function phase03(): void
    {
        $this->post(route('purchase-returns.store'), [
            'number' => $this->n(),
            'purchase_id' => $this->purchase->id,
            'date' => $this->today,
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'scenario',
            'item_list' => [['purchase_item_id' => $this->purchase->items->first()->id, 'quantity' => 1]],
        ])->assertRedirect();

        $return = PurchaseReturn::query()->orderByDesc('id')->firstOrFail();

        $step = 'P03a purchase return 1 carton';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 148);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 11400);

        $movement = StockMovement::query()
            ->where('reference_type', PurchaseReturn::class)
            ->where('reference_id', $return->id)
            ->firstOrFail();

        $this->expect($step, 'return movement unit cost', (float) $movement->unit_cost, 600);
        $this->snapshot($step);

        // ---- step 12 of the checklist ----
        $step = 'P03b reverse the purchase return';
        $this->act($step, route('purchase-returns.reverse', $return), ['reason' => 'scenario']);
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 160);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12000);
        $this->expect($step, 'red average', $this->avg($this->red), 50);
        $this->snapshot($step);
    }

    private function phase04(): void
    {
        $sale = $this->sell(120);

        $step = 'P04a sale 120';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 40);
        $this->expect($step, 'COGS', $this->cogsOf($sale), 6000);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 6000);
        $this->expect($step, 'red average', $this->avg($this->red), 50);
        $this->snapshot($step);

        $this->post(route('sale-returns.store'), [
            'number' => $this->n(),
            'sale_id' => $sale->id,
            'date' => $this->today,
            'reason' => SaleReturnReason::values()[0],
            'description' => 'scenario',
            'item_list' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => 20]],
        ])->assertRedirect();

        $saleReturn = SaleReturn::query()->orderByDesc('id')->firstOrFail();

        $step = 'P04b sale return 20';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 60);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 7000);
        $this->snapshot($step);

        $step = 'P04c reverse both';
        $this->act($step, route('sale-returns.reverse', $saleReturn), ['reason' => 'scenario']);
        $this->act($step, route('sales.reverse', $sale), ['reason' => 'scenario']);
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 160);
        $this->expect($step, 'total on hand', $this->onHand(), 210);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12000);
        $this->expect($step, 'red average', $this->avg($this->red), 50);
        $this->snapshot($step);

        $step = 'P04d probe sale of 10';
        $probe = $this->sell(10);
        $this->expect($step, 'COGS', $this->cogsOf($probe), 500);
        $this->act($step, route('sales.reverse', $probe), ['reason' => 'scenario']);
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 160);
        $this->snapshot($step);
    }

    private function phase05(): void
    {
        $out = $this->adjust(10, 'damage');

        $step = 'P05a adjustment OUT 10';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 150);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 11500);
        $this->snapshot($step);

        $step = 'P05b reverse the OUT adjustment';
        $this->act($step, route('stock-adjustments.reverse', $out), ['reason' => 'scenario']);
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 160);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12000);
        $this->expect($step, 'red average', $this->avg($this->red), 50);
        $this->snapshot($step);

        $in = $this->adjust(5, 'found', 50);

        $step = 'P05c adjustment IN 5 @ 50';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 165);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12250);
        $this->snapshot($step);

        $step = 'P05c reverse the IN adjustment';
        $this->act($step, route('stock-adjustments.reverse', $in), ['reason' => 'scenario']);
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 160);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12000);
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
            'transfer_cost' => 200,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'items' => [[
                'item_id' => $this->item->id,
                'variant_id' => $this->red->id,
                'quantity' => 40,
                'measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 50,
            ]],
        ])->assertRedirect(route('item-transfers.index'));

        $transfer = ItemTransfer::query()->orderByDesc('id')->firstOrFail();

        $step = 'P06 transfer 40 to the second warehouse';
        $this->expect($step, 'main warehouse', $this->onHand($this->ctx['warehouse']->id, $this->red->id), 120);
        $this->expect($step, 'second warehouse', $this->onHand($this->second->id, $this->red->id), 40);
        $this->expect($step, 'total on hand', $this->onHand(), 210);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12000);
        $this->expect($step, 'transfer expense', $this->gl('item-transfer-expense'), 200);
        $this->snapshot($step);

        $step = 'P06e reverse the transfer';
        $this->act($step, route('item-transfers.reverse', $transfer), ['reason' => 'scenario']);
        $this->expect($step, 'main warehouse', $this->onHand($this->ctx['warehouse']->id, $this->red->id), 160);
        $this->expect($step, 'second warehouse', $this->onHand($this->second->id, $this->red->id), 0);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 12000);
        $this->expect($step, 'transfer expense', $this->gl('item-transfer-expense'), 0);
        $this->snapshot($step);

        $step = 'P06e probe sale of 10';
        $probe = $this->sell(10);
        $this->expect($step, 'COGS', $this->cogsOf($probe), 500);
        $this->act($step, route('sales.reverse', $probe), ['reason' => 'scenario']);
        $this->snapshot($step);
    }

    private function phase07(): void
    {
        $this->secondPurchase = $this->buy(40, 80);

        $step = 'P07a purchase 40 @ 80';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 200);
        $this->expect($step, 'red average', $this->avg($this->red), 56);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 15200);
        $this->snapshot($step);

        $sale = $this->sell(180);

        $step = 'P07b sale 180 across two layers';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 20);
        $this->expect($step, 'COGS', $this->cogsOf($sale), 9600);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 5600);

        $rows = StockMovement::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('movement_type', StockMovementType::OUT->value)
            ->orderBy('id')
            ->get(['quantity', 'unit_cost']);

        // Three layers, not two: the opening and the carton purchase are
        // separate receipts that happen to share a cost, and the second
        // purchase at 80 is the third.
        if ($rows->count() !== 3) {
            $this->flag($step, 'the sale produced ' . $rows->count() . ' OUT rows, expected 3');
        }

        foreach ([[100.0, 50.0], [60.0, 50.0], [20.0, 80.0]] as $i => [$qty, $cost]) {
            $row = $rows[$i] ?? null;

            if ($row === null || ! $this->near((float) $row->quantity, $qty) || ! $this->near((float) $row->unit_cost, $cost)) {
                $this->flag($step, sprintf(
                    'OUT row %d is %s, expected %.0f @ %.0f',
                    $i + 1,
                    $row === null ? 'missing' : sprintf('%.0f @ %.0f', (float) $row->quantity, (float) $row->unit_cost),
                    $qty,
                    $cost,
                ));
            }
        }

        $this->snapshot($step);

    }

    private Purchase $secondPurchase;

    private function phase08(): void
    {
        $sale = $this->sell(20);

        $step = 'P08a sell the last 20';
        $this->expect($step, 'red on hand', $this->onHand(null, $this->red->id), 0);
        $this->expect($step, 'COGS', $this->cogsOf($sale), 1600);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 4000);
        $this->snapshot($step);

        $this->buy(10, 120);

        $step = 'P08b restock 10 @ 120 on an empty shelf';
        $this->expect($step, 'red average', $this->avg($this->red), 120);
        $this->expect($step, 'inventory GL', $this->gl('inventory-stock'), 5200);
        $this->snapshot($step);
    }

    private function phase09(): void
    {
        $step = 'P09a sell 999';
        if ($this->act($step, route('sales.store'), [
            'number' => $this->n(),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => $this->today,
            'transaction_total' => 999 * 90,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->item->id,
                'variant_id' => $this->red->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => 999,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 90,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])) {
            $this->flag($step, 'selling 999 from a shelf of 10 was ALLOWED');
        } else {
            $this->clearExpectedRejection($step);
        }

        $step = 'P09b reverse a sold-through purchase';
        if ($this->act($step, route('purchases.reverse', $this->secondPurchase), ['reason' => 'scenario'])) {
            $this->flag($step, 'reversing a purchase whose goods are gone was ALLOWED');
        } else {
            $this->clearExpectedRejection($step);
        }

        $this->snapshot('P09 after the refusals');
    }

    /** A refusal is the expected answer in phase 09, so it is not a finding. */
    private function clearExpectedRejection(string $step): void
    {
        $this->findings = array_values(array_filter(
            $this->findings,
            fn (string $f) => ! str_starts_with($f, $step . ' :: rejected:'),
        ));
    }
}
