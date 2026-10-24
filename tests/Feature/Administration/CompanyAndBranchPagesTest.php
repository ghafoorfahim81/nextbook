<?php

namespace Tests\Feature\Administration;

use App\Models\Administration\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The company and branch pages: each has a show, an edit and (for a branch) a
 * create page, reached from the workspace pickers. There is no separate list
 * for either, and the Administration menu no longer links to them.
 */
class CompanyAndBranchPagesTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    public function test_the_old_company_address_and_branch_list_lead_to_the_new_pages(): void
    {
        $this->get('/company')->assertRedirect(route('companies.show', $this->ctx['company']));
        $this->get(route('branches.index'))->assertRedirect(route('workspace.branches'));
    }

    public function test_the_company_page_shows_the_company(): void
    {
        $this->get(route('companies.show', $this->ctx['company']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Company/Show')
                ->where('company.id', (string) $this->ctx['company']->id)
                ->where('isActive', true)
                ->where('canEdit', true)
                ->has('branches'));
    }

    public function test_the_edit_page_opens_filled_in_with_the_companys_settings(): void
    {
        $this->get(route('companies.edit', $this->ctx['company']))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Company/Edit')
                ->where('company.name_en', $this->ctx['company']->name_en)
                ->where('company.currency_id', $this->ctx['company']->currency_id)
                ->has('settings.sale')
                ->where('canEditSettings', true));
    }

    public function test_saving_the_edit_page_changes_the_company_and_its_settings(): void
    {
        $company = $this->ctx['company'];

        $this->put(route('companies.update', $company), [
            'name_en' => 'Renamed Trading',
            'abbreviation' => 'RT',
            'business_type' => $company->business_type->value,
            'calendar_type' => $company->calendar_type->value,
            'working_style' => $company->working_style->value,
            'locale' => $company->locale->value,
            'costing_method' => $company->costing_method->value,
            'currency_id' => $this->ctx['currency']->id,
            'phone' => '0700000000',
            'license_number' => 'LIC-77',
            'settings' => ['sale' => ['invoice_prefix' => 'RT-']],
        ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('companies.show', $company));

        $company->refresh();

        $this->assertSame('Renamed Trading', $company->name_en);
        $this->assertSame('LIC-77', $company->license_number);
        $this->assertSame('RT-', data_get($company->preferences, 'sale.invoice_prefix'));
    }

    public function test_the_edit_page_clears_a_field_left_empty(): void
    {
        $company = $this->ctx['company'];
        $company->update(['phone' => '0711111111']);

        $this->put(route('companies.update', $company), [
            'name_en' => $company->name_en,
            'abbreviation' => $company->abbreviation ?: 'NB',
            'business_type' => $company->business_type->value,
            'calendar_type' => $company->calendar_type->value,
            'working_style' => $company->working_style->value,
            'locale' => $company->locale->value,
            'costing_method' => $company->costing_method->value,
            'currency_id' => $this->ctx['currency']->id,
            'phone' => '',
        ])->assertSessionHasNoErrors();

        $this->assertNull($company->fresh()->phone);
    }

    public function test_a_branch_is_created_in_the_company_being_worked_in(): void
    {
        $this->get(route('branches.create'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Branch/Create')->has('parentOptions'));

        $this->post(route('branches.store'), ['name' => 'Herat', 'location' => 'Herat city'])
            ->assertSessionHasNoErrors();

        $branch = Branch::query()->where('name', 'Herat')->firstOrFail();

        $this->assertSame((string) $this->ctx['company']->id, (string) $branch->company_id);
    }

    public function test_the_branch_pages_show_edit_and_delete_a_branch(): void
    {
        // Made directly: a branch created through the form gets a warehouse, and
        // a branch with a warehouse cannot be deleted (Branch::getRelationships).
        Branch::create(['name' => 'Mazar', 'company_id' => $this->ctx['company']->id]);
        $branch = Branch::query()->where('name', 'Mazar')->firstOrFail();

        $this->get(route('branches.show', $branch))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Branch/Show')
                ->where('branch.name', 'Mazar')
                ->where('can.update', true)
                ->where('can.delete', true));

        $this->get(route('branches.edit', $branch))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('Branch/Edit')->where('branch.id', (string) $branch->id));

        $this->put(route('branches.update', $branch), ['name' => 'Mazar-e-Sharif', 'location' => 'North'])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('branches.show', $branch));
        $this->assertSame('Mazar-e-Sharif', $branch->fresh()->name);

        $this->delete(route('branches.destroy', $branch))->assertRedirect(route('workspace.branches'));
        $this->assertSoftDeleted($branch);
    }

    public function test_the_branch_picker_offers_each_card_and_a_new_branch(): void
    {
        $this->get(route('workspace.branches'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page
                ->component('Workspace/Branches')
                ->where('canCreate', true)
                ->where('branches.0.can_view', true)
                ->where('branches.0.can_edit', true));
    }
}
