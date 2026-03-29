<?php

namespace Tests\Unit\Services;

use App\Enums\CostingMethod;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Inventory\StockBalance;
use App\Models\Inventory\StockMovement;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class StockServiceTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext(CostingMethod::FIFO->value);
    }

    public function test_fifo_deducts_oldest_layers_first(): void
    {
        $service = app(StockService::class);

        $common = [
            'item_id' => $this->ctx['item']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'source' => StockSourceType::PURCHASE->value,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'fifo-test',
            'reference_id' => $this->ctx['item']->id,
        ];

        $service->post(array_merge($common, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 5,
            'unit_cost' => 10,
            'date' => '2026-03-01',
        ]));

        $service->post(array_merge($common, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 5,
            'unit_cost' => 20,
            'date' => '2026-03-02',
        ]));

        $service->post(array_merge($common, [
            'movement_type' => StockMovementType::OUT->value,
            'quantity' => 6,
            'unit_cost' => 0,
            'date' => '2026-03-03',
        ]));

        $outMovements = StockMovement::query()
            ->where('movement_type', StockMovementType::OUT->value)
            ->orderBy('created_at')
            ->get();

        $this->assertEquals(2, $outMovements->count());
        $this->assertEquals(5.0, (float) $outMovements[0]->quantity);
        $this->assertEquals(10.0, (float) $outMovements[0]->unit_cost);
        $this->assertEquals(1.0, (float) $outMovements[1]->quantity);
        $this->assertEquals(20.0, (float) $outMovements[1]->unit_cost);

        $balance = StockBalance::query()->firstOrFail();
        $this->assertEquals(4.0, (float) $balance->quantity);
    }

    public function test_weighted_average_out_uses_balance_average_cost_when_method_is_not_fifo(): void
    {
        $this->ctx = $this->bootstrapErpContext(CostingMethod::LIFO->value);
        $service = app(StockService::class);

        $base = [
            'item_id' => $this->ctx['item']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'source' => StockSourceType::PURCHASE->value,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'avg-test',
            'reference_id' => $this->ctx['item']->id,
        ];

        $service->post(array_merge($base, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 10,
            'unit_cost' => 10,
            'date' => '2026-03-01',
        ]));

        $service->post(array_merge($base, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 10,
            'unit_cost' => 20,
            'date' => '2026-03-02',
        ]));

        $service->post(array_merge($base, [
            'movement_type' => StockMovementType::OUT->value,
            'quantity' => 4,
            'unit_cost' => 0,
            'date' => '2026-03-03',
        ]));

        $out = StockMovement::query()
            ->where('movement_type', StockMovementType::OUT->value)
            ->latest()
            ->firstOrFail();

        $this->assertEquals(15.0, round((float) $out->unit_cost, 2));
    }

    public function test_batch_tracked_items_require_batch_number(): void
    {
        $service = app(StockService::class);
        $this->ctx['item']->update(['is_batch_tracked' => true]);

        $this->expectException(ValidationException::class);

        $service->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 5,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'date' => '2026-03-01',
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'batch-test',
            'reference_id' => $this->ctx['item']->id,
        ]);
    }

    public function test_sequential_out_requests_prevent_negative_stock(): void
    {
        $service = app(StockService::class);

        $base = [
            'item_id' => $this->ctx['item']->id,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'source' => StockSourceType::PURCHASE->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'size_id' => $this->ctx['size']->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'race-test',
            'reference_id' => $this->ctx['item']->id,
        ];

        $service->post(array_merge($base, [
            'movement_type' => StockMovementType::IN->value,
            'quantity' => 5,
            'date' => '2026-03-01',
        ]));

        $service->post(array_merge($base, [
            'movement_type' => StockMovementType::OUT->value,
            'quantity' => 4,
            'date' => '2026-03-02',
        ]));

        $this->expectException(ValidationException::class);

        $service->post(array_merge($base, [
            'movement_type' => StockMovementType::OUT->value,
            'quantity' => 4,
            'date' => '2026-03-03',
        ]));
    }
}
