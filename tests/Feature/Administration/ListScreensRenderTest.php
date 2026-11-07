<?php

namespace Tests\Feature\Administration;

use App\Models\Administration\CustomerGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Screens that feed DataTable, and the shape they feed it.
 *
 * DataTable reads its pagination figures from `meta` when the page sends an API
 * Resource collection and from the top level when it sends a raw paginator.
 * These two send the raw shape; the assertions below pin the keys the table
 * needs so a controller change cannot quietly take them away.
 */
class ListScreensRenderTest extends TestCase
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

    public function test_the_customer_groups_list_renders_with_usable_pagination(): void
    {
        CustomerGroup::factory()->create([
            'company_id' => $this->ctx['company']->id,
            'name_en' => 'Wholesale',
        ]);

        $this->get(route('customer-groups.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Administration/CustomerGroups/Index')
                ->has('customerGroups.data', 1)
                ->has('customerGroups.current_page')
                ->has('customerGroups.per_page')
                ->has('customerGroups.last_page')
                ->has('customerGroups.total'));
    }

    public function test_a_customer_group_can_be_created_under_row_level_security(): void
    {
        $this->post(route('customer-groups.store'), [
            'name_en' => 'VIP',
            'local_name' => 'ویژه',
        ])->assertSessionHasNoErrors();

        $group = CustomerGroup::query()->where('name_en', 'VIP')->firstOrFail();
        $this->assertSame($this->ctx['company']->id, $group->company_id);
    }

    public function test_a_payment_term_can_be_created_under_row_level_security(): void
    {
        $this->post(route('payment-terms.store'), [
            'name' => 'Net 30',
            'days' => 30,
            'type' => 'net',
        ]);

        $this->assertDatabaseHas('payment_terms', [
            'name' => 'Net 30',
            'company_id' => $this->ctx['company']->id,
        ]);
    }

    public function test_a_payment_term_can_be_edited_and_deleted(): void
    {
        $this->post(route('payment-terms.store'), ['name' => 'Net 15', 'days' => 15, 'type' => 'net']);
        $term = \App\Models\Administration\PaymentTerm::query()->where('name', 'Net 15')->firstOrFail();

        $this->patch(route('payment-terms.update', $term->id), ['name' => 'Net 45', 'days' => 45, 'type' => 'net'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Net 45', $term->fresh()->name);
        $this->assertSame(45, $term->fresh()->days);

        $this->delete(route('payment-terms.destroy', $term->id))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('payment_terms', ['id' => $term->id]);
    }

    public function test_a_customer_group_can_be_edited_and_deleted(): void
    {
        $this->post(route('customer-groups.store'), ['name_en' => 'Retail', 'local_name' => 'پرچون']);
        $group = CustomerGroup::query()->where('name_en', 'Retail')->firstOrFail();

        $this->patch(route('customer-groups.update', $group->id), ['name_en' => 'Retail 2', 'local_name' => 'پرچون ۲'])
            ->assertSessionHasNoErrors();
        $this->assertSame('Retail 2', $group->fresh()->name_en);

        $this->delete(route('customer-groups.destroy', $group->id))->assertSessionHasNoErrors();
        $this->assertSoftDeleted('customer_groups', ['id' => $group->id]);
    }

    public function test_the_payment_terms_list_renders_with_usable_pagination(): void
    {
        $this->get(route('payment-terms.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('paymentTerms.data')
                ->has('paymentTerms.current_page')
                ->has('paymentTerms.per_page')
                ->has('paymentTerms.last_page')
                ->has('paymentTerms.total'));
    }

    public function test_the_landed_cost_create_screen_renders(): void
    {
        $this->get(route('landed-costs.create'))->assertOk();
    }
}
