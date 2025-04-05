<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Category;
use App\Models\CreatedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\CategoryController
 */
final class CategoryControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $categories = Category::factory()->count(3)->create();

        $response = $this->get(route('categories.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\CategoryController::class,
            'store',
            \App\Http\Requests\Administration\CategoryStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('categories.store'), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $categories = Category::query()
            ->where('name', $name)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $categories);
        $category = $categories->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $category = Category::factory()->create();

        $response = $this->get(route('categories.show', $category));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\CategoryController::class,
            'update',
            \App\Http\Requests\Administration\CategoryUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $category = Category::factory()->create();
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('categories.update', $category), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $category->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $category->name);
        $this->assertEquals($created_by->id, $category->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $category = Category::factory()->create();

        $response = $this->delete(route('categories.destroy', $category));

        $response->assertNoContent();

        $this->assertModelMissing($category);
    }
}
