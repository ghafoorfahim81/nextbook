<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Branch;
use App\Models\CreatedBy;
use App\Models\Quantity;
use App\Models\UnitMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\UnitMeasureController
 */
final class UnitMeasureControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $unitMeasures = UnitMeasure::factory()->count(3)->create();

        $response = $this->get(route('unit-measures.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\UnitMeasureController::class,
            'store',
            \App\Http\Requests\Administration\UnitMeasureStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $unit = fake()->word();
        $symbol = fake()->word();
        $branch = Branch::factory()->create();
        $quantity = Quantity::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('unit-measures.store'), [
            'name' => $name,
            'unit' => $unit,
            'symbol' => $symbol,
            'branch_id' => $branch->id,
            'quantity_id' => $quantity->id,
            'created_by' => $created_by->id,
        ]);

        $unitMeasures = UnitMeasure::query()
            ->where('name', $name)
            ->where('unit', $unit)
            ->where('symbol', $symbol)
            ->where('branch_id', $branch->id)
            ->where('quantity_id', $quantity->id)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $unitMeasures);
        $unitMeasure = $unitMeasures->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $unitMeasure = UnitMeasure::factory()->create();

        $response = $this->get(route('unit-measures.show', $unitMeasure));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\UnitMeasureController::class,
            'update',
            \App\Http\Requests\Administration\UnitMeasureUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $unitMeasure = UnitMeasure::factory()->create();
        $name = fake()->name();
        $unit = fake()->word();
        $symbol = fake()->word();
        $branch = Branch::factory()->create();
        $quantity = Quantity::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('unit-measures.update', $unitMeasure), [
            'name' => $name,
            'unit' => $unit,
            'symbol' => $symbol,
            'branch_id' => $branch->id,
            'quantity_id' => $quantity->id,
            'created_by' => $created_by->id,
        ]);

        $unitMeasure->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $unitMeasure->name);
        $this->assertEquals($unit, $unitMeasure->unit);
        $this->assertEquals($symbol, $unitMeasure->symbol);
        $this->assertEquals($branch->id, $unitMeasure->branch_id);
        $this->assertEquals($quantity->id, $unitMeasure->quantity_id);
        $this->assertEquals($created_by->id, $unitMeasure->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $unitMeasure = UnitMeasure::factory()->create();

        $response = $this->delete(route('unit-measures.destroy', $unitMeasure));

        $response->assertNoContent();

        $this->assertModelMissing($unitMeasure);
    }
}
