<?php

namespace Tests\Feature\Inventory;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Account\Account;
use App\Models\Inventory\StockAdjustment;
use App\Models\Transaction\Transaction;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class StockAdjustmentFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Account $shrinkageAccount;

    private Account $adjustmentsAccount;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        Cache::put('costing_method', 'fifo');

        $this->shrinkageAccount = Account::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Inventory Shrinkage & Wastage',
            'number' => '9040',
            'slug' => 'inventory-shrinkage-and-wastage',
            'account_type_id' => $this->ctx['account_types']['expense']->id,
            'is_main' => true,
            'is_active' => true,
        ]);

        $this->adjustmentsAccount = Account::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Inventory Adjustments',
            'number' => '9050',
            'slug' => 'inventory-adjustments',
            'account_type_id' => $this->ctx['account_types']['expense']->id,
            'is_main' => true,
            'is_active' => true,
        ]);
    }

    private function seedStock(float $quantity = 10, float $unitCost = 15): void
    {
        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => $quantity,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => $unitCost,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-10',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed-adjustment-stock',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    private function transactionFor(StockAdjustment $adjustment): Transaction
    {
        return Transaction::query()
            ->where('reference_type', StockAdjustment::class)
            ->where('reference_id', $adjustment->id)
            ->whereNull('reversal_of_id')
            ->firstOrFail();
    }

    public function test_out_adjustment_decreases_stock_and_posts_balanced_gl(): void
    {
        $this->seedStock(10, 15);

        $response = $this->post(route('stock-adjustments.store'), [
            'date' => '2026-03-19',
            'reason' => 'damage',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'notes' => 'broken in storage',
            'items' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'quantity' => 4,
                ],
            ],
        ]);

        $response->assertRedirect(route('stock-adjustments.index'));

        $adjustment = StockAdjustment::query()->latest()->firstOrFail();
        $this->assertEquals(TransactionStatus::POSTED->value, $adjustment->status);
        $this->assertEquals('out', $adjustment->type->value);
        $this->assertStringStartsWith('ADJ-2026-', $adjustment->reference);

        $this->assertDatabaseHas('stock_balances', [
            'item_id' => $this->ctx['item']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'quantity' => 6.0000,
        ]);

        $transaction = $this->transactionFor($adjustment);
        $this->assertEquals(TransactionStatus::POSTED->value, (string) $transaction->status);

        // OUT: debit 9040 shrinkage, credit inventory — 4 x 15 = 60.
        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $this->shrinkageAccount->id,
            'debit' => 60.0,
        ]);
        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'credit' => 60.0,
        ]);
    }

    public function test_in_adjustment_increases_stock_and_credits_offset_account(): void
    {
        $this->seedStock(10, 15);

        $response = $this->post(route('stock-adjustments.store'), [
            'date' => '2026-03-20',
            'reason' => 'found',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'quantity' => 5,
                    'unit_cost' => 20,
                ],
            ],
        ]);

        $response->assertRedirect(route('stock-adjustments.index'));

        $adjustment = StockAdjustment::query()->latest()->firstOrFail();
        $this->assertEquals('in', $adjustment->type->value);
        $this->assertEquals(TransactionStatus::POSTED->value, $adjustment->status);

        // 10 seeded + 5 found.
        $total = (float) \App\Models\Inventory\StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->sum('quantity');
        $this->assertEqualsWithDelta(15.0, $total, 0.0001);

        $transaction = $this->transactionFor($adjustment);

        // IN: debit inventory, credit 9050 adjustments — 5 x 20 = 100.
        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $this->ctx['accounts']['inventory-stock']->id,
            'debit' => 100.0,
        ]);
        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $this->adjustmentsAccount->id,
            'credit' => 100.0,
        ]);
    }

    public function test_reverse_restores_stock_and_marks_adjustment_reversed(): void
    {
        $this->seedStock(10, 15);

        $this->post(route('stock-adjustments.store'), [
            'date' => '2026-03-21',
            'reason' => 'wastage',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'quantity' => 3,
                ],
            ],
        ]);

        $adjustment = StockAdjustment::query()->latest()->firstOrFail();
        $this->assertEquals(TransactionStatus::POSTED->value, $adjustment->status);

        $response = $this->post(route('stock-adjustments.reverse', $adjustment), [
            'reason' => 'entered by mistake',
        ]);
        $response->assertRedirect();

        $adjustment->refresh();
        $this->assertEquals(TransactionStatus::REVERSED->value, $adjustment->status);

        // Stock restored to the original 10.
        $total = (float) \App\Models\Inventory\StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->sum('quantity');
        $this->assertEqualsWithDelta(10.0, $total, 0.0001);

        // A reversal transaction mirrors the original lines.
        $original = Transaction::query()
            ->where('reference_type', StockAdjustment::class)
            ->where('reference_id', $adjustment->id)
            ->firstOrFail();
        $this->assertEquals(TransactionStatus::REVERSED->value, (string) $original->status);

        $reversal = Transaction::query()->where('reversal_of_id', $original->id)->firstOrFail();
        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $reversal->id,
            'account_id' => $this->shrinkageAccount->id,
            'credit' => 45.0,
        ]);
    }

    public function test_draft_adjustment_reserves_stock_and_posts_later(): void
    {
        $this->seedStock(10, 15);

        $user = $this->ctx['user'];
        $user->setPreference('transaction.stock_adjustment_post_immediately', false)->save();

        $this->post(route('stock-adjustments.store'), [
            'date' => '2026-03-22',
            'reason' => 'theft',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'items' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'quantity' => 2,
                ],
            ],
        ]);

        $adjustment = StockAdjustment::query()->latest()->firstOrFail();
        $this->assertEquals(TransactionStatus::DRAFT->value, $adjustment->status);

        // Draft holds a reservation; on-hand quantity unchanged.
        $balance = \App\Models\Inventory\StockBalance::query()
            ->where('item_id', $this->ctx['item']->id)
            ->where('warehouse_id', $this->ctx['warehouse']->id)
            ->firstOrFail();
        $this->assertEqualsWithDelta(10.0, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(2.0, (float) $balance->reserved_out, 0.0001);

        $postResponse = $this->post(route('stock-adjustments.post', $adjustment));
        $postResponse->assertRedirect();

        $adjustment->refresh();
        $this->assertEquals(TransactionStatus::POSTED->value, $adjustment->status);

        $balance->refresh();
        $this->assertEqualsWithDelta(8.0, (float) $balance->quantity, 0.0001);
        $this->assertEqualsWithDelta(0.0, (float) $balance->reserved_out, 0.0001);
    }
}
