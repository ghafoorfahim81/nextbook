<?php

namespace Tests\Feature\Http\Controllers\LedgerOpening;

use App\Models\CreatedBy;
use App\Models\LedgerOpening;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\LedgerOpening\LedgerOpeningController
 */
final class LedgerOpeningControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $ledgerOpenings = LedgerOpening::factory()->count(3)->create();

        $response = $this->get(route('ledger-openings.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\LedgerOpening\LedgerOpeningController::class,
            'store',
            \App\Http\Requests\LedgerOpening\LedgerOpeningStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $transactionable = fake()->word();
        $ledgerable = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('ledger-openings.store'), [
            'transactionable' => $transactionable,
            'ledgerable' => $ledgerable,
            'created_by' => $created_by->id,
        ]);

        $ledgerOpenings = LedgerOpening::query()
            ->where('transactionable', $transactionable)
            ->where('ledgerable', $ledgerable)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $ledgerOpenings);
        $ledgerOpening = $ledgerOpenings->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $ledgerOpening = LedgerOpening::factory()->create();

        $response = $this->get(route('ledger-openings.show', $ledgerOpening));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\LedgerOpening\LedgerOpeningController::class,
            'update',
            \App\Http\Requests\LedgerOpening\LedgerOpeningUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $ledgerOpening = LedgerOpening::factory()->create();
        $transactionable = fake()->word();
        $ledgerable = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('ledger-openings.update', $ledgerOpening), [
            'transactionable' => $transactionable,
            'ledgerable' => $ledgerable,
            'created_by' => $created_by->id,
        ]);

        $ledgerOpening->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($transactionable, $ledgerOpening->transactionable);
        $this->assertEquals($ledgerable, $ledgerOpening->ledgerable);
        $this->assertEquals($created_by->id, $ledgerOpening->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $ledgerOpening = LedgerOpening::factory()->create();

        $response = $this->delete(route('ledger-openings.destroy', $ledgerOpening));

        $response->assertNoContent();

        $this->assertModelMissing($ledgerOpening);
    }
}
