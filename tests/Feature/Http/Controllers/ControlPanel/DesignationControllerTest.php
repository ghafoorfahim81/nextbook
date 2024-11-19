<?php

namespace Tests\Feature\Http\Controllers\ControlPanel;

use App\Models\CreatedBy;
use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\ControlPanel\DesignationController
 */
final class DesignationControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $designations = Designation::factory()->count(3)->create();

        $response = $this->get(route('designations.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ControlPanel\DesignationController::class,
            'store',
            \App\Http\Requests\ControlPanel\DesignationStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = $this->faker->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('designations.store'), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $designations = Designation::query()
            ->where('name', $name)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $designations);
        $designation = $designations->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $designation = Designation::factory()->create();

        $response = $this->get(route('designations.show', $designation));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\ControlPanel\DesignationController::class,
            'update',
            \App\Http\Requests\ControlPanel\DesignationUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $designation = Designation::factory()->create();
        $name = $this->faker->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('designations.update', $designation), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $designation->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $designation->name);
        $this->assertEquals($created_by->id, $designation->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $designation = Designation::factory()->create();

        $response = $this->delete(route('designations.destroy', $designation));

        $response->assertNoContent();

        $this->assertSoftDeleted($designation);
    }
}
