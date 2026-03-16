<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\WarehouseController
 */
final class StoreControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $warehouses = Warehouse::factory()->count(3)->create();

        $response = $this->get(route('warehouses.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\WarehouseController::class,
            'store',
            \App\Http\Requests\Administration\WarehouseStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $is_main = fake()->boolean();
        $branch = Branch::factory()->create();
        $created_by = User::factory()->create();

        $response = $this->post(route('warehouses.store'), [
            'name' => $name,
            'is_main' => $is_main,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $warehouses = Warehouse::query()
            ->where('name', $name)
            ->where('is_main', $is_main)
            ->where('branch_id', $branch->id)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $warehouses);
        $warehouse = $warehouses->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $warehouse = Warehouse::factory()->create();

        $response = $this->get(route('warehouses.show', $warehouse));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\WarehouseController::class,
            'update',
            \App\Http\Requests\Administration\WarehouseUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $warehouse = Warehouse::factory()->create();
        $name = fake()->name();
        $is_main = fake()->boolean();
        $branch = Branch::factory()->create();
        $created_by = User::factory()->create();

        $response = $this->put(route('warehouses.update', $warehouse), [
            'name' => $name,
            'is_main' => $is_main,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $warehouse->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $warehouse->name);
        $this->assertEquals($is_main, $warehouse->is_main);
        $this->assertEquals($branch->id, $warehouse->branch_id);
        $this->assertEquals($created_by->id, $warehouse->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $warehouse = Warehouse::factory()->create();

        $response = $this->delete(route('warehouses.destroy', $warehouse));

        $response->assertNoContent();

        $this->assertModelMissing($warehouse);
    }
}
