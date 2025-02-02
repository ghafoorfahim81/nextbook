<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\CreatedBy;
use App\Models\Department;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\DepartmentController
 */
final class DepartmentControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $departments = Department::factory()->count(3)->create();

        $response = $this->get(route('departments.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\DepartmentController::class,
            'store',
            \App\Http\Requests\Administration\DepartmentStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = $this->faker->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('departments.store'), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $departments = Department::query()
            ->where('name', $name)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $departments);
        $department = $departments->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $department = Department::factory()->create();

        $response = $this->get(route('departments.show', $department));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\DepartmentController::class,
            'update',
            \App\Http\Requests\Administration\DepartmentUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $department = Department::factory()->create();
        $name = $this->faker->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('departments.update', $department), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $department->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $department->name);
        $this->assertEquals($created_by->id, $department->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $department = Department::factory()->create();

        $response = $this->delete(route('departments.destroy', $department));

        $response->assertNoContent();

        $this->assertModelMissing($department);
    }
}
