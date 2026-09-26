<?php

namespace Tests\Integration;

use App\Enums\PurchaseReturnReason;
use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Models\Account\Account;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Inventory\StockAdjustment;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
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
 * One item through every stock document, checking the three things that must
 * never disagree: the quantity on the shelf, the inventory account, and the
 * FIFO layers behind them.
 *
 * Written as a single walk rather than one test per document because the
 * failures it was built for only appear in combination — a reversal on its own
 * looks fine, and it is the next document, costed off what the reversal left
 * behind, that goes wrong. Every step is collected and reported together, so a
 * failure names every invariant that broke rather than only the first.
 */
class StockLifecycleIntegrityTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Item $item;

    private array $report = [];

    private array $findings = [];

    private int $docNumber = 1000;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        Cache::put('costing_method', 'fifo');

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
    }

    // ---------------------------------------------------------------- probes

    private function onHand(): float
    {
        return (float) StockBalance::query()
            ->where('item_id', $this->item->id)
            ->sum('quantity');
    }

    /** What the live FIFO layers say the shelf is worth. */
    private function layerState(): array
    {
        $layers = StockMovement::query()
            ->where('item_id', $this->item->id)
            ->where('movement_type', StockMovementType::IN->value)
            ->whereNotIn('status', [StockStatus::VOIDED->value, StockStatus::CANCELLED->value])
            ->where('qty_remaining', '>', 0)
            ->get(['quantity', 'qty_remaining', 'unit_cost']);

        return [
            'qty' => (float) $layers->sum('qty_remaining'),
            'value' => (float) $layers->sum(fn ($l) => (float) $l->qty_remaining * (float) $l->unit_cost),
        ];
    }

    /** The inventory account's balance, the way the ledger reports it. */
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

    private function itemAvg(): float
    {
        return (float) $this->item->fresh()->avg_cost;
    }

    private function variantAvg(): float
    {
        return (float) ItemVariant::query()
            ->where('item_id', $this->item->id)
            ->orderBy('sort_order')
            ->value('avg_cost');
    }

    private function flag(string $step, string $issue): void
    {
        $this->findings[] = $step . ' :: ' . $issue;
    }

    /**
     * Post and notice when the request bounced.
     *
     * A failed action redirects back with errors, which looks exactly like a
     * successful redirect — so every call reports what it actually did.
     */
    private function act(string $step, string $url, array $payload = []): void
    {
        $response = $this->post($url, $payload);
        $errors = session('errors');

        if ($errors && count($errors->all()) > 0) {
            $this->flag($step, 'the request was REJECTED: ' . implode(' | ', $errors->all()));
            session()->forget('errors');
        }

        if (session('error')) {
            $this->flag($step, 'the request was REJECTED: ' . session('error'));
            session()->forget('error');
        }

        if (! in_array($response->getStatusCode(), [200, 201, 302], true)) {
            $this->flag($step, 'HTTP ' . $response->getStatusCode());
        }
    }

    /** Every movement this item has, for the tail of the report. */
    private function dumpMovements(): string
    {
        $rows = StockMovement::query()
            ->where('item_id', $this->item->id)
            ->orderBy('date')
            ->orderBy('id')
            ->get(['movement_type', 'source', 'status', 'quantity', 'unit_cost', 'qty_remaining', 'reference_type']);

        $lines = [sprintf(
            '%-4s %-16s %-10s %9s %10s %11s  %s',
            'DIR', 'SOURCE', 'STATUS', 'QTY', 'UNIT COST', 'REMAINING', 'DOCUMENT'
        )];

        foreach ($rows as $row) {
            $lines[] = sprintf(
                '%-4s %-16s %-10s %9.2f %10.2f %11s  %s',
                $row->movement_type->value === 'in' ? 'IN' : 'OUT',
                $row->source->value,
                $row->status->value,
                (float) $row->quantity,
                (float) $row->unit_cost,
                $row->qty_remaining === null ? '-' : sprintf('%.2f', (float) $row->qty_remaining),
                class_basename((string) $row->reference_type) ?: '-',
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Record the state after a step and check the invariants that must hold
     * for the books and the warehouse to agree.
     */
    private function check(string $step, float $expectedQty, float $expectedValue): void
    {
        $onHand = $this->onHand();
        $layers = $this->layerState();
        $gl = $this->glInventory();
        $avg = $this->itemAvg();
        $variantAvg = $this->variantAvg();
        $stockValue = $onHand * $avg;

        $this->report[] = sprintf(
            "%-34s qty=%7.2f (want %7.2f) | GL=%10.2f (want %10.2f) | avg=%9.4f | qty*avg=%10.2f | layers=%7.2f/%10.2f | variantAvg=%9.4f",
            $step,
            $onHand,
            $expectedQty,
            $gl,
            $expectedValue,
            $avg,
            $stockValue,
            $layers['qty'],
            $layers['value'],
            $variantAvg,
        );

        $near = fn (float $a, float $b) => abs($a - $b) < 0.01;

        if (! $near($onHand, $expectedQty)) {
            $this->flag($step, sprintf('on hand is %.2f, expected %.2f', $onHand, $expectedQty));
        }

        if (! $near($gl, $expectedValue)) {
            $this->flag($step, sprintf('inventory GL is %.2f, expected %.2f', $gl, $expectedValue));
        }

        if (! $near($stockValue, $gl)) {
            $this->flag($step, sprintf(
                'qty x avg_cost = %.2f but the inventory GL holds %.2f (gap %.2f)',
                $stockValue,
                $gl,
                $stockValue - $gl,
            ));
        }

        if (! $near($layers['qty'], $onHand)) {
            $this->flag($step, sprintf(
                'live FIFO layers hold %.2f but the balance says %.2f',
                $layers['qty'],
                $onHand,
            ));
        }

        if (! $near($layers['value'], $gl)) {
            $this->flag($step, sprintf(
                'live FIFO layers are worth %.2f but the inventory GL holds %.2f',
                $layers['value'],
                $gl,
            ));
        }

        if (! $near($avg, $variantAvg)) {
            $this->flag($step, sprintf(
                'item average is %.4f but its only variant reads %.4f',
                $avg,
                $variantAvg,
            ));
        }
    }

    // ------------------------------------------------------------- documents

    private function nextNumber(): int
    {
        return ++$this->docNumber;
    }

    private function createItemWithOpening(float $quantity, float $unitPrice): void
    {
        $this->post(route('items.store'), [
            'name' => 'انرژی هیت',
            'code' => 'EH-001',
            'item_type' => 'inventory_materials',
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'asset_account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'income_account_id' => $this->ctx['accounts']['product-income']->id,
            'cost_account_id' => $this->ctx['accounts']['cost-of-goods-sold']->id,
            'sale_price' => 200,
            'purchase_price' => $unitPrice,
            'is_batch_tracked' => false,
            'is_expiry_tracked' => false,
            'variants' => [
                ['attributes' => [], 'sku' => 'EH-DEF', 'purchase_price' => $unitPrice, 'is_default' => true, 'sort_order' => 0],
            ],
            'openings' => [[
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'warehouse_id' => $this->ctx['warehouse']->id,
                'batch' => null,
                'expire_date' => null,
                'variant_index' => 0,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->item = Item::where('code', 'EH-001')->firstOrFail();
    }

    private function buy(float $quantity, float $unitPrice): Purchase
    {
        $this->post(route('purchases.store'), [
            'number' => $this->nextNumber(),
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-10',
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
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $unitPrice,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect();

        return Purchase::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function sell(float $quantity, float $unitPrice = 400): Sale
    {
        $this->post(route('sales.store'), [
            'number' => $this->nextNumber(),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-15',
            'transaction_total' => $quantity * $unitPrice,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->item->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $unitPrice,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function adjust(float $quantity, string $reason, ?float $unitCost = null): StockAdjustment
    {
        $line = [
            'item_id' => $this->item->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
        ];

        if ($unitCost !== null) {
            $line['unit_cost'] = $unitCost;
        }

        $this->post(route('stock-adjustments.store'), [
            'date' => '2026-03-25',
            'reason' => $reason,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'notes' => 'audit',
            'items' => [$line],
        ])->assertRedirect(route('stock-adjustments.index'));

        return StockAdjustment::query()->orderByDesc('id')->firstOrFail();
    }

    // ------------------------------------------------------------- the audit

    public function test_the_books_and_the_warehouse_agree_through_every_document(): void
    {
        // ---- opening: 10 pieces at 100 -------------------------------------
        $this->createItemWithOpening(10, 100);
        $this->check('01 opening 10 @ 100', 10, 1000);

        // ---- 1. purchase ---------------------------------------------------
        $purchase = $this->buy(5, 120);
        $this->check('02 purchase 5 @ 120', 15, 1600);

        // ---- 2. purchase return --------------------------------------------
        $purchaseReturn = $this->post(route('purchase-returns.store'), [
            'number' => $this->nextNumber(),
            'purchase_id' => $purchase->id,
            'date' => '2026-03-12',
            'reason' => PurchaseReturnReason::values()[0],
            'description' => 'audit',
            'item_list' => [[
                'purchase_item_id' => $purchase->items->first()->id,
                'quantity' => 2,
            ]],
        ])->assertRedirect() ? PurchaseReturn::query()->orderByDesc('id')->firstOrFail() : null;
        $this->check('03 purchase return 2 @ 120', 13, 1360);

        // ---- 4. reversing the purchase return ------------------------------
        $this->act('04 reverse purchase return', route('purchase-returns.reverse', $purchaseReturn), ['reason' => 'audit']);
        $this->check('04 reverse purchase return', 15, 1600);

        // ---- 3. reversing the purchase -------------------------------------
        $this->act('05 reverse purchase', route('purchases.reverse', $purchase), ['reason' => 'audit']);
        $this->check('05 reverse purchase', 10, 1000);

        // ---- 5. sale -------------------------------------------------------
        $sale = $this->sell(4);
        $this->check('06 sale 4 (cost 100)', 6, 600);

        // ---- 6. reversing the sale -----------------------------------------
        $this->act('07 reverse sale', route('sales.reverse', $sale), ['reason' => 'audit']);
        $this->check('07 reverse sale', 10, 1000);

        // ---- 7. sale return -------------------------------------------------
        $sale2 = $this->sell(4);
        $this->check('08 sale 4 again', 6, 600);

        $this->post(route('sale-returns.store'), [
            'number' => $this->nextNumber(),
            'sale_id' => $sale2->id,
            'date' => '2026-03-18',
            'reason' => SaleReturnReason::values()[0],
            'description' => 'audit',
            'item_list' => [[
                'sale_item_id' => $sale2->items->first()->id,
                'quantity' => 2,
            ]],
        ])->assertRedirect();
        $saleReturn = SaleReturn::query()->orderByDesc('id')->firstOrFail();
        $this->check('09 sale return 2', 8, 800);

        // ---- 8. reversing the sale return ----------------------------------
        $this->act('10 reverse sale return', route('sale-returns.reverse', $saleReturn), ['reason' => 'audit']);
        $this->check('10 reverse sale return', 6, 600);

        // ---- 9. stock adjustment IN ----------------------------------------
        $adjustmentIn = $this->adjust(3, 'found', 100);
        $this->check('11 adjustment IN 3 @ 100', 9, 900);

        // ---- 10. reversing the IN adjustment -------------------------------
        $this->act('12 reverse adjustment IN', route('stock-adjustments.reverse', $adjustmentIn), ['reason' => 'audit']);
        $this->check('12 reverse adjustment IN', 6, 600);

        // ---- 9b. stock adjustment OUT --------------------------------------
        $adjustmentOut = $this->adjust(2, 'damage');
        $this->check('13 adjustment OUT 2', 4, 400);

        // ---- 10b. reversing the OUT adjustment -----------------------------
        $this->act('14 reverse adjustment OUT', route('stock-adjustments.reverse', $adjustmentOut), ['reason' => 'audit']);
        $this->check('14 reverse adjustment OUT', 6, 600);

        // ---- 15. can the item still be sold at all? -------------------------
        // Every live FIFO layer is gone by now while 6 pieces sit on the shelf,
        // so this is the question that decides whether the state is merely
        // untidy or actually unusable.
        $before = $this->onHand();
        $this->act('15 sell the remaining 6', route('sales.store'), [
            'number' => $this->nextNumber(),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-28',
            'transaction_total' => 6 * 400,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->item->id,
                'batch' => null,
                'expire_date' => null,
                'quantity' => 6,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 400,
                'item_discount' => 0,
                'free' => 0,
                'tax' => 0,
            ]],
        ]);

        if (abs($this->onHand() - $before) < 0.01) {
            $this->flag('15 sell the remaining 6', 'the sale did not move any stock — on hand is still ' . $before);
        }

        $this->check('15 sell remaining 6', 0, 0);

        if ($this->findings === []) {
            $this->assertSame([], $this->findings);

            return;
        }

        // Only on a failure: the walk in full, so the broken step can be read
        // in the context of the ones around it.
        fwrite(STDERR, "\n\n================ STOCK LIFECYCLE ================\n");
        fwrite(STDERR, implode("\n", $this->report) . "\n");
        fwrite(STDERR, "\n---------------- DOCUMENT STATUS ----------------\n");
        foreach ([
            'purchase' => $purchase->fresh()->status,
            'purchase return' => $purchaseReturn->fresh()->status,
            'sale' => $sale->fresh()->status,
            'sale (2nd)' => $sale2->fresh()->status,
            'sale return' => $saleReturn->fresh()->status,
            'adjustment IN' => $adjustmentIn->fresh()->status,
            'adjustment OUT' => $adjustmentOut->fresh()->status,
        ] as $label => $status) {
            $value = $status instanceof \BackedEnum ? $status->value : (string) $status;
            fwrite(STDERR, sprintf("%-18s %s\n", $label, $value));
        }

        fwrite(STDERR, "\n---------------- MOVEMENT LEDGER ----------------\n");
        fwrite(STDERR, $this->dumpMovements() . "\n");
        fwrite(STDERR, "\n---------------- FINDINGS (" . count($this->findings) . ") ----------------\n");
        fwrite(STDERR, $this->findings === []
            ? "none\n"
            : implode("\n", $this->findings) . "\n");
        fwrite(STDERR, "=================================================\n\n");

        $this->fail(
            count($this->findings) . ' invariant(s) broke — see the walk above:' . PHP_EOL
            . implode(PHP_EOL, $this->findings)
        );
    }
}
