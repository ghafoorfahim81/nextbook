<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Administration\Brand;
use App\Models\Administration\Branch;
use App\Models\Administration\CreatedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\BrandController
 */
final class BrandControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $brands = Brand::factory()->count(3)->create();

        $response = $this->get(route('brands.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\BrandController::class,
            'store',
            \App\Http\Requests\Administration\BrandStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('brands.store'), [
            'name' => $name,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $brands = Brand::query()
            ->where('name', $name)
            ->where('branch_id', $branch->id)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $brands);
        $brand = $brands->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->get(route('brands.show', $brand));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\BrandController::class,
            'update',
            \App\Http\Requests\Administration\BrandUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $brand = Brand::factory()->create();
        $name = fake()->name();
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('brands.update', $brand), [
            'name' => $name,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $brand->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $brand->name);
        $this->assertEquals($branch->id, $brand->branch_id);
        $this->assertEquals($created_by->id, $brand->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $brand = Brand::factory()->create();

        $response = $this->delete(route('brands.destroy', $brand));

        $response->assertNoContent();

        $this->assertModelMissing($brand);
    }
}
