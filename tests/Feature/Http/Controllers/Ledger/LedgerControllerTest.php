<?php

namespace Tests\Feature\Http\Controllers\Ledger;

use App\Models\CreatedBy;
use App\Models\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Ledger\LedgerController
 */
final class LedgerControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $ledgers = Ledger::factory()->count(3)->create();

        $response = $this->get(route('ledgers.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Ledger\LedgerController::class,
            'store',
            \App\Http\Requests\Ledger\LedgerStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('ledgers.store'), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $ledgers = Ledger::query()
            ->where('name', $name)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $ledgers);
        $ledger = $ledgers->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $ledger = Ledger::factory()->create();

        $response = $this->get(route('ledgers.show', $ledger));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Ledger\LedgerController::class,
            'update',
            \App\Http\Requests\Ledger\LedgerUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $ledger = Ledger::factory()->create();
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('ledgers.update', $ledger), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $ledger->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $ledger->name);
        $this->assertEquals($created_by->id, $ledger->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $ledger = Ledger::factory()->create();

        $response = $this->delete(route('ledgers.destroy', $ledger));

        $response->assertNoContent();

        $this->assertModelMissing($ledger);
    }
}
