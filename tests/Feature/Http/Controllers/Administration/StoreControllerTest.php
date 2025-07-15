<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Branch;
use App\Models\CreatedBy;
use App\Models\Store;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\StoreController
 */
final class StoreControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $stores = Store::factory()->count(3)->create();

        $response = $this->get(route('stores.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\StoreController::class,
            'store',
            \App\Http\Requests\Administration\StoreStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $is_main = fake()->boolean();
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('stores.store'), [
            'name' => $name,
            'is_main' => $is_main,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $stores = Store::query()
            ->where('name', $name)
            ->where('is_main', $is_main)
            ->where('branch_id', $branch->id)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $stores);
        $store = $stores->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $store = Store::factory()->create();

        $response = $this->get(route('stores.show', $store));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\StoreController::class,
            'update',
            \App\Http\Requests\Administration\StoreUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $store = Store::factory()->create();
        $name = fake()->name();
        $is_main = fake()->boolean();
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('stores.update', $store), [
            'name' => $name,
            'is_main' => $is_main,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $store->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $store->name);
        $this->assertEquals($is_main, $store->is_main);
        $this->assertEquals($branch->id, $store->branch_id);
        $this->assertEquals($created_by->id, $store->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $store = Store::factory()->create();

        $response = $this->delete(route('stores.destroy', $store));

        $response->assertNoContent();

        $this->assertModelMissing($store);
    }
}
