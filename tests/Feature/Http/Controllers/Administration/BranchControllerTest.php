<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Branch;
use App\Models\CreatedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\BranchController
 */
final class BranchControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $branches = Branch::factory()->count(3)->create();

        $response = $this->get(route('branches.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\BranchController::class,
            'store',
            \App\Http\Requests\Administration\BranchStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $is_main = fake()->boolean();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('branches.store'), [
            'name' => $name,
            'is_main' => $is_main,
            'created_by' => $created_by->id,
        ]);

        $branches = Branch::query()
            ->where('name', $name)
            ->where('is_main', $is_main)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $branches);
        $branch = $branches->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->get(route('branches.show', $branch));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\BranchController::class,
            'update',
            \App\Http\Requests\Administration\BranchUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $branch = Branch::factory()->create();
        $name = fake()->name();
        $is_main = fake()->boolean();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('branches.update', $branch), [
            'name' => $name,
            'is_main' => $is_main,
            'created_by' => $created_by->id,
        ]);

        $branch->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $branch->name);
        $this->assertEquals($is_main, $branch->is_main);
        $this->assertEquals($created_by->id, $branch->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $branch = Branch::factory()->create();

        $response = $this->delete(route('branches.destroy', $branch));

        $response->assertNoContent();

        $this->assertModelMissing($branch);
    }
}
