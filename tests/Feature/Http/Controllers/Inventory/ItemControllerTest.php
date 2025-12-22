<?php

namespace Tests\Feature\Http\Controllers\Inventory;

use App\Models\Branch;
use App\Models\Category;
use App\Models\Administration\Brand;
use App\Models\CreatedBy;
use App\Models\Item;
use App\Models\UnitMeasure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Inventory\ItemController
 */
final class ItemControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $items = Item::factory()->count(3)->create();

        $response = $this->get(route('items.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Inventory\ItemController::class,
            'store',
            \App\Http\Requests\Inventory\ItemStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $code = fake()->word();
        $unit_measure = UnitMeasure::factory()->create();
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        $sale_price = fake()->randomFloat(/** double_attributes **/);
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('items.store'), [
            'name' => $name,
            'code' => $code,
            'unit_measure_id' => $unit_measure->id,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'sale_price' => $sale_price,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $items = Item::query()
            ->where('name', $name)
            ->where('code', $code)
            ->where('unit_measure_id', $unit_measure->id)
            ->where('brand_id', $brand->id)
            ->where('category_id', $category->id)
            ->where('sale_price', $sale_price)
            ->where('branch_id', $branch->id)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $items);
        $item = $items->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $item = Item::factory()->create();

        $response = $this->get(route('items.show', $item));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Inventory\ItemController::class,
            'update',
            \App\Http\Requests\Inventory\ItemUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $item = Item::factory()->create();
        $name = fake()->name();
        $code = fake()->word();
        $unit_measure = UnitMeasure::factory()->create();
        $brand = Brand::factory()->create();
        $category = Category::factory()->create();
        $sale_price = fake()->randomFloat(/** double_attributes **/);
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('items.update', $item), [
            'name' => $name,
            'code' => $code,
            'unit_measure_id' => $unit_measure->id,
            'brand_id' => $brand->id,
            'category_id' => $category->id,
            'sale_price' => $sale_price,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $item->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $item->name);
        $this->assertEquals($code, $item->code);
        $this->assertEquals($unit_measure->id, $item->unit_measure_id);
        $this->assertEquals($brand->id, $item->brand_id);
        $this->assertEquals($category->id, $item->category_id);
        $this->assertEquals($sale_price, $item->sale_price);
        $this->assertEquals($branch->id, $item->branch_id);
        $this->assertEquals($created_by->id, $item->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $item = Item::factory()->create();

        $response = $this->delete(route('items.destroy', $item));

        $response->assertNoContent();

        $this->assertModelMissing($item);
    }
}
