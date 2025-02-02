<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Designation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\DesignationController
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
            \App\Http\Controllers\Administration\DesignationController::class,
            'store',
            \App\Http\Requests\Administration\DesignationStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $response = $this->post(route('designations.store'));

        $response->assertCreated();
        $response->assertJsonStructure([]);

        $this->assertDatabaseHas(designations, [ /* ... */ ]);
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
            \App\Http\Controllers\Administration\DesignationController::class,
            'update',
            \App\Http\Requests\Administration\DesignationUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $designation = Designation::factory()->create();

        $response = $this->put(route('designations.update', $designation));

        $designation->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $designation = Designation::factory()->create();

        $response = $this->delete(route('designations.destroy', $designation));

        $response->assertNoContent();

        $this->assertModelMissing($designation);
    }
}
