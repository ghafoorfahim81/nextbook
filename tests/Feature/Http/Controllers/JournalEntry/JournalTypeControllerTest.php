<?php

namespace Tests\Feature\Http\Controllers\JournalEntry;

use App\Models\CreatedBy;
use App\Models\JournalType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\JournalEntry\JournalTypeController
 */
final class JournalTypeControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $journalTypes = JournalType::factory()->count(3)->create();

        $response = $this->get(route('journal-types.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\JournalEntry\JournalTypeController::class,
            'store',
            \App\Http\Requests\JournalEntry\JournalTypeStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('journal-types.store'), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $journalTypes = JournalType::query()
            ->where('name', $name)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $journalTypes);
        $journalType = $journalTypes->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $journalType = JournalType::factory()->create();

        $response = $this->get(route('journal-types.show', $journalType));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\JournalEntry\JournalTypeController::class,
            'update',
            \App\Http\Requests\JournalEntry\JournalTypeUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $journalType = JournalType::factory()->create();
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('journal-types.update', $journalType), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $journalType->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $journalType->name);
        $this->assertEquals($created_by->id, $journalType->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $journalType = JournalType::factory()->create();

        $response = $this->delete(route('journal-types.destroy', $journalType));

        $response->assertNoContent();

        $this->assertModelMissing($journalType);
    }
}
