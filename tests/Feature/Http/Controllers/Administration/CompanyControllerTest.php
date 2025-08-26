<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Company;
use App\Models\CreatedBy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\CompanyController
 */
final class CompanyControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $companies = Company::factory()->count(3)->create();

        $response = $this->get(route('companies.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\CompanyController::class,
            'store',
            \App\Http\Requests\Administration\CompanyStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name_en = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('companies.store'), [
            'name_en' => $name_en,
            'created_by' => $created_by->id,
        ]);

        $companies = Company::query()
            ->where('name_en', $name_en)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $companies);
        $company = $companies->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $company = Company::factory()->create();

        $response = $this->get(route('companies.show', $company));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\CompanyController::class,
            'update',
            \App\Http\Requests\Administration\CompanyUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $company = Company::factory()->create();
        $name_en = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('companies.update', $company), [
            'name_en' => $name_en,
            'created_by' => $created_by->id,
        ]);

        $company->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name_en, $company->name_en);
        $this->assertEquals($created_by->id, $company->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $company = Company::factory()->create();

        $response = $this->delete(route('companies.destroy', $company));

        $response->assertNoContent();

        $this->assertModelMissing($company);
    }
}
