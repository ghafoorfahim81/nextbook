<?php

namespace Tests\Integration;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Warehouse;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Models\ItemTransfer\ItemTransfer;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Services\ItemVariantService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The awkward corners, where the easy cases already pass.
 *
 * Each case combines two things that are fine on their own: a second unit of
 * measure with a FIFO reversal, a reversal with the goods already gone, an
 * emptied shelf with a restock at a new price. The invariant checked
 * throughout is the one that actually matters for a FIFO item — the live
 * layers must hold exactly what is on the shelf, and be worth exactly what the
 * inventory account says.
 */
class StockEdgeCaseScenarioTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Item $item;

    private UnitMeasure $box;

    private array $findings = [];

    private int $docNumber = 3000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        Cache::put('costing_method', 'fifo');

        // A second unit in the same family: one box is twelve pieces. Quantity
        // and unit_cost are recorded in whichever unit the document chose,
        // while qty_remaining is kept in the item's own — the seam where a
        // conversion bug hides.
        $this->box = UnitMeasure::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'quantity_id' => $this->ctx['quantity']->id,
            'name' => 'Box',
            'unit' => '12',
            'symbol' => 'box',
            'is_main' => false,
            'is_active' => true,
        ]);

        $this->item = $this->ctx['item'];
        app(ItemVariantService::class)->ensureDefault($this->item);
    }

    // ---------------------------------------------------------------- probes

    private function onHand(?string $warehouseId = null): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->item->id)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->sum('quantity');
    }

    /** Live layers, in the item's own unit, with what they are worth. */
    private function layers(?string $warehouseId = null): array
    {
        $rows = StockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('movement_type', StockMovementType::IN->value)
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->where('qty_remaining', '>', 0)
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->get(['unit_measure_id', 'quantity', 'qty_remaining', 'unit_cost']);

        $units = UnitMeasure::withTrashed()
            ->whereIn('id', $rows->pluck('unit_measure_id')->unique())
            ->pluck('unit', 'id');

        $itemUnit = (float) UnitMeasure::withTrashed()
            ->whereKey($this->item->unit_measure_id)
            ->value('unit') ?: 1.0;

        $value = 0.0;
        foreach ($rows as $row) {
            $factor = (float) ($units[$row->unit_measure_id] ?? $itemUnit) / $itemUnit;
            $factor = $factor > 0 ? $factor : 1.0;
            // qty_remaining is already in the item's unit; the cost is not.
            $value += (float) $row->qty_remaining * ((float) $row->unit_cost / $factor);
        }

        return ['qty' => (float) $rows->sum('qty_remaining'), 'value' => $value];
    }

    private function glInventory(): float
    {
        return (float) DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transaction_lines.account_id', $this->ctx['accounts']['inventory-stock']->id)
            ->whereIn('transactions.status', ['posted', 'reversed'])
            ->whereNull('transaction_lines.deleted_at')
            ->selectRaw('COALESCE(SUM(transaction_lines.debit - transaction_lines.credit), 0) AS balance')
            ->value('balance');
    }

    private function flag(string $step, string $issue): void
    {
        $this->findings[] = $step . ' :: ' . $issue;
    }

    private function check(string $step, float $expectedQty, ?float $expectedGl = null): void
    {
        $onHand = $this->onHand();
        $layers = $this->layers();
        $gl = $this->glInventory();
        $near = fn (float $a, float $b) => abs($a - $b) < 0.01;

        if (! $near($onHand, $expectedQty)) {
            $this->flag($step, sprintf('on hand is %.2f, expected %.2f', $onHand, $expectedQty));
        }

        if ($expectedGl !== null && ! $near($gl, $expectedGl)) {
            $this->flag($step, sprintf('inventory GL is %.2f, expected %.2f', $gl, $expectedGl));
        }

        if (! $near($layers['qty'], $onHand)) {
            $this->flag($step, sprintf(
                'live layers hold %.2f but the shelf has %.2f',
                $layers['qty'],
                $onHand,
            ));
        }

        if (! $near($layers['value'], $gl)) {
            $this->flag($step, sprintf(
                'live layers are worth %.2f but the inventory GL holds %.2f',
                $layers['value'],
                $gl,
            ));
        }
    }

    private function report(): void
    {
        if ($this->findings === []) {
            $this->assertSame([], $this->findings);

            return;
        }

        $this->fail(
            count($this->findings) . ' problem(s):' . PHP_EOL . implode(PHP_EOL, $this->findings)
        );
    }

    // ------------------------------------------------------------- documents

    private function nextNumber(): int
    {
        return ++$this->docNumber;
    }

    private function open(float $quantity, float $unitCost, ?string $warehouseId = null, ?string $variantId = null): void
    {
        app(StockService::class)->post([
            'item_id' => $this->item->id,
            'variant_id' => $variantId,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => '2026-01-05',
            'warehouse_id' => $warehouseId ?? $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);

        // The opening above moves stock without a voucher, so the ledger is
        // given the matching entry by hand — otherwise every later comparison
        // against the inventory account starts out short.
        app(\App\Services\TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-01-05',
                'remark' => 'opening',
                'status' => \App\Enums\TransactionStatus::POSTED->value,
                'branch_id' => $this->ctx['branch']->id,
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['inventory-stock']->id, 'debit' => $quantity * $unitCost, 'credit' => 0, 'remark' => 'opening'],
                ['account_id' => $this->ctx['accounts']['opening-balance-equity']->id, 'debit' => 0, 'credit' => $quantity * $unitCost, 'remark' => 'opening'],
            ],
        );
    }

    private function buy(float $quantity, float $unitPrice, ?UnitMeasure $unit = null): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => $this->nextNumber(),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-02-10',
            'transaction_total' => $quantity * $unitPrice,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->item->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => ($unit ?? $this->ctx['unit_measure'])->id,
                'unit_price' => $unitPrice,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function sell(float $quantity, ?UnitMeasure $unit = null, ?string $variantId = null): Sale
    {
        $line = [
            'item_id' => $this->item->id,
            'batch' => null,
            'expire_date' => null,
            'quantity' => $quantity,
            'unit_measure_id' => ($unit ?? $this->ctx['unit_measure'])->id,
            'unit_price' => 99,
            'item_discount' => 0,
            'free' => 0,
            'tax' => 0,
        ];

        if ($variantId) {
            $line['variant_id'] = $variantId;
        }

        $this->post(route('sales.store'), [
            'number' => $this->nextNumber(),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-15',
            'transaction_total' => $quantity * 99,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [$line],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    /** Post and report a rejection rather than letting it pass unnoticed. */
    /** What the last refusal told the operator. */
    private ?string $lastRefusal = null;

    private function act(string $step, string $url, array $payload = []): bool
    {
        $this->post($url, $payload);

        $rejected = false;
        $this->lastRefusal = null;

        if (($errors = session('errors')) && count($errors->all()) > 0) {
            $this->lastRefusal = implode(' | ', $errors->all());
            $this->flag($step, 'REJECTED: ' . implode(' | ', $errors->all()));
            session()->forget('errors');
            $rejected = true;
        }

        if (session('error')) {
            $this->lastRefusal = (string) session('error');
            $this->flag($step, 'REJECTED: ' . session('error'));
            session()->forget('error');
            $rejected = true;
        }

        return ! $rejected;
    }

    // ----------------------------------------------------------------- cases

    public function test_a_reversal_gives_back_layers_across_two_units_and_two_layers(): void
    {
        // 24 pieces at 10, then 2 boxes at 150 — the same goods priced per box
        // (12.50 a piece), so the two layers disagree on cost AND on unit.
        $this->open(24, 10);
        $this->check('opening 24 pc @ 10', 24, 240);

        $this->buy(2, 150, $this->box);
        $this->check('purchase 2 box @ 150', 48, 540);

        // Straddles both layers: all 24 of the first, 6 pieces out of the second.
        $sale = $this->sell(30);
        $this->check('sell 30 pc', 18, 225);

        $this->act('reverse the sale', route('sales.reverse', $sale), ['reason' => 'edge case']);
        $this->check('reverse the sale', 48, 540);

        // Both layers have to be whole again, in the item's own unit.
        $opening = StockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('source', StockSourceType::OPENING->value)
            ->firstOrFail();

        if (abs((float) $opening->qty_remaining - 24) > 0.01) {
            $this->flag('reverse the sale', sprintf(
                'the opening layer holds %.2f of its 24',
                (float) $opening->qty_remaining,
            ));
        }

        $boxLayer = StockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('source', StockSourceType::PURCHASE->value)
            ->where('movement_type', StockMovementType::IN->value)
            ->firstOrFail();

        if (abs((float) $boxLayer->qty_remaining - 24) > 0.01) {
            $this->flag('reverse the sale', sprintf(
                'the box layer holds %.2f of its 24 pieces',
                (float) $boxLayer->qty_remaining,
            ));
        }

        $this->report();
    }

    public function test_reversing_a_purchase_whose_goods_have_already_been_sold_is_refused(): void
    {
        $this->open(10, 10);
        $purchase = $this->buy(10, 20);
        $this->check('bought 10 @ 20', 20, 300);

        // Eats the opening layer and half the purchase.
        $this->sell(15);
        $this->check('sold 15', 5, 100);

        // Only 5 of the 10 purchased pieces are still here. Un-receiving all
        // ten would take the warehouse below zero, so the right answer is to
        // refuse — the operator has to reverse the sale first, or raise a
        // return for what is left.
        $allowed = $this->act('reverse the purchase', route('purchases.reverse', $purchase), ['reason' => 'edge case']);

        if ($allowed) {
            $this->flag(
                'reverse the purchase',
                'it went through, although only 5 of the 10 received pieces are still on the shelf',
            );
        }

        // A refusal on its own is not enough: it has to say what is wrong and
        // what to do next, or the screen just appears to ignore the click.
        $this->assertNotNull($this->lastRefusal, 'The refusal carried no message at all.');
        $this->assertSame(
            __('general.cannot_reverse_stock_already_gone'),
            $this->lastRefusal,
            'A reversal blocked by missing stock must say so in its own words.',
        );

        // A refusal is the expected answer, so it is not a finding.
        $this->findings = array_values(array_filter(
            $this->findings,
            fn (string $f) => ! str_contains($f, 'reverse the purchase :: REFUSED')
                && ! str_contains($f, 'reverse the purchase :: REJECTED'),
        ));

        // And a refusal must leave everything exactly as it was.
        $this->check('after the refusal', 5, 100);

        $this->report();
    }

    public function test_selling_more_than_the_shelf_holds_says_so_by_name(): void
    {
        $this->open(12, 10);

        $this->post(route('sales.store'), [
            'number' => $this->nextNumber(),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-15',
            'transaction_total' => 999 * 99,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->item->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => 999,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 99,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ]);

        $errors = session('errors');

        $this->assertNotNull($errors, 'Over-selling must be refused.');

        $message = implode(' ', $errors->all());

        // The operator needs the item, the warehouse and the two numbers —
        // this used to read "Insufficient stock. item_id 01m3... available: 12
        // required: 999", which names the item only by its primary key.
        $this->assertStringContainsString($this->item->name, $message);
        $this->assertStringContainsString($this->ctx['warehouse']->name, $message);
        $this->assertStringContainsString('12', $message);
        $this->assertStringContainsString('999', $message);
        $this->assertStringNotContainsString($this->item->id, $message);
    }

    public function test_an_emptied_shelf_restocks_at_the_new_price_rather_than_a_blend(): void
    {
        $this->open(10, 10);
        $this->sell(10);
        $this->check('sold out', 0, 0);

        $this->buy(5, 50);
        $this->check('restocked 5 @ 50', 5, 250);

        $average = (float) $this->item->fresh()->avg_cost;

        if (abs($average - 50) > 0.01) {
            $this->flag('restocked 5 @ 50', sprintf(
                'the average is %.4f — stock that arrived on an empty shelf is worth what it cost, not a blend with goods that are gone',
                $average,
            ));
        }

        $this->report();
    }

    public function test_two_variants_keep_their_own_layers_and_averages(): void
    {
        $cheap = app(ItemVariantService::class)->ensureDefault($this->item);
        $pricy = ItemVariant::create([
            'item_id' => $this->item->id,
            'branch_id' => $this->ctx['branch']->id,
            'attributes' => ['grade' => 'premium'],
            'variant_key' => 'grade:premium',
            'sku' => 'EDGE-PRICY',
            'purchase_price' => 30,
            'sale_price' => 60,
            'is_default' => false,
            'is_active' => true,
            'sort_order' => 1,
            'created_by' => $this->ctx['user']->id,
        ]);

        $this->open(10, 10, variantId: $cheap->id);
        $this->open(10, 30, variantId: $pricy->id);
        $this->check('two variants stocked', 20, 400);

        $this->sell(4, variantId: $pricy->id);
        $this->check('sold 4 of the pricy one', 16, 280);

        if (abs((float) $cheap->fresh()->avg_cost - 10) > 0.01) {
            $this->flag('sold 4 of the pricy one', sprintf(
                'selling one variant moved the average of the other, to %.4f',
                (float) $cheap->fresh()->avg_cost,
            ));
        }

        if (abs((float) $pricy->fresh()->avg_cost - 30) > 0.01) {
            $this->flag('sold 4 of the pricy one', sprintf(
                'the sold variant now reads %.4f instead of 30',
                (float) $pricy->fresh()->avg_cost,
            ));
        }

        $cheapLayer = StockMovement::query()
            ->where('variant_id', $cheap->id)
            ->where('movement_type', StockMovementType::IN->value)
            ->firstOrFail();

        if (abs((float) $cheapLayer->qty_remaining - 10) > 0.01) {
            $this->flag('sold 4 of the pricy one', sprintf(
                'it came out of the other variant\'s layer, which is down to %.2f',
                (float) $cheapLayer->qty_remaining,
            ));
        }

        $this->report();
    }

    public function test_a_transfer_and_its_reversal_leave_both_warehouses_whole(): void
    {
        set_user_preference('transaction.item_transfer_post_immediately', true, $this->ctx['user']);

        $second = Warehouse::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Overflow Warehouse',
        ]);

        $this->open(20, 10);
        $this->check('20 in the main warehouse', 20, 200);

        $this->post(route('item-transfers.store'), [
            'date' => '2026-03-20',
            'from_warehouse_id' => $this->ctx['warehouse']->id,
            'to_warehouse_id' => $second->id,
            'remarks' => 'edge case',
            'items' => [[
                'item_id' => $this->item->id,
                'quantity' => 8,
                'measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
            ]],
        ])->assertRedirect(route('item-transfers.index'));

        $transfer = ItemTransfer::query()->orderByDesc('id')->firstOrFail();

        // Moving stock between our own shelves changes nothing in the ledger.
        $this->check('transferred 8 out', 20, 200);

        if (abs($this->onHand($this->ctx['warehouse']->id) - 12) > 0.01) {
            $this->flag('transferred 8 out', sprintf(
                'the source warehouse has %.2f, expected 12',
                $this->onHand($this->ctx['warehouse']->id),
            ));
        }

        if (abs($this->layers($this->ctx['warehouse']->id)['qty'] - 12) > 0.01) {
            $this->flag('transferred 8 out', sprintf(
                'the source warehouse layers hold %.2f against 12 on its shelf',
                $this->layers($this->ctx['warehouse']->id)['qty'],
            ));
        }

        $this->act('reverse the transfer', route('item-transfers.reverse', $transfer), ['reason' => 'edge case']);

        $this->check('reverse the transfer', 20, 200);

        if (abs($this->onHand($second->id)) > 0.01) {
            $this->flag('reverse the transfer', sprintf(
                'the destination still holds %.2f',
                $this->onHand($second->id),
            ));
        }

        if (abs($this->layers($this->ctx['warehouse']->id)['qty'] - 20) > 0.01) {
            $this->flag('reverse the transfer', sprintf(
                'the source warehouse layers hold %.2f against 20 on its shelf',
                $this->layers($this->ctx['warehouse']->id)['qty'],
            ));
        }

        $this->report();
    }
}
