<?php

namespace Tests\Feature\Http\Controllers\JournalEntry;

use App\Models\CreatedBy;
use App\Models\JournalEntry\JournalClass;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\JournalEntry\JournalClassController
 */
final class JournalClassControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $journalClasses = JournalClass::factory()->count(3)->create();

        $response = $this->get(route('journal-classes.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\JournalEntry\JournalClassController::class,
            'store',
            \App\Http\Requests\JournalEntry\JournalClassStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('journal-classes.store'), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $journalClasses = JournalClass::query()
            ->where('name', $name)
            ->where('created_by', $created_by->id)
            ->get();
                $this->assertCount(1, $journalClasses);
        $journalClass = $journalClasses->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $journalClass = JournalClass::factory()->create();

        $response = $this->get(route('journal-classes.show', $journalClass));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\JournalEntry\JournalClassController::class,
            'update',
            \App\Http\Requests\JournalEntry\JournalClassUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $journalClass = JournalClass::factory()->create();
        $name = fake()->name();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('journal-classes.update', $journalClass), [
            'name' => $name,
            'created_by' => $created_by->id,
        ]);

        $journalClass->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $journalClass->name);
        $this->assertEquals($created_by->id, $journalClass->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
                $journalClass = JournalClass::factory()->create();

        $response = $this->delete(route('journal-classes.destroy', $journalClass));

        $response->assertNoContent();

        $this->assertModelMissing($journalClass);
    }
}
