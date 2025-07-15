<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Quantity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\QuantityController
 */
final class QuantityControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $quantities = Quantity::factory()->count(3)->create();

        $response = $this->get(route('quantities.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\QuantityController::class,
            'store',
            \App\Http\Requests\Administration\QuantityStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $quantity = fake()->word();

        $response = $this->post(route('quantities.store'), [
            'quantity' => $quantity,
        ]);

        $quantities = Quantity::query()
            ->where('quantity', $quantity)
            ->get();
        $this->assertCount(1, $quantities);
        $quantity = $quantities->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $quantity = Quantity::factory()->create();

        $response = $this->get(route('quantities.show', $quantity));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\QuantityController::class,
            'update',
            \App\Http\Requests\Administration\QuantityUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $quantity = Quantity::factory()->create();
        $quantity = fake()->word();

        $response = $this->put(route('quantities.update', $quantity), [
            'quantity' => $quantity,
        ]);

        $quantity->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($quantity, $quantity->quantity);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $quantity = Quantity::factory()->create();

        $response = $this->delete(route('quantities.destroy', $quantity));

        $response->assertNoContent();

        $this->assertModelMissing($quantity);
    }
}
