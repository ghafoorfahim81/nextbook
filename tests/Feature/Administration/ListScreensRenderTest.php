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
            'branch_id' => $this->ctx['branch']->id,
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
