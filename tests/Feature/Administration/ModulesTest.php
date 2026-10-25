<?php

namespace Tests\Feature\Administration;

use App\Models\Administration\Company;
use App\Support\Modules;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Phase 6: a company switches modules on and off (config/modules.php). One
 * that is off is hidden and blocked, its data left alone.
 */
class ModulesTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_a_module_that_is_off_cannot_be_reached_by_its_url(): void
    {
        $this->get(route('employees.index'))->assertOk();

        set_company_setting('modules.hr', false, $this->ctx['company']);

        // The app's own Forbidden page, saying why.
        $this->get(route('employees.index'))
            ->assertForbidden()
            ->assertInertia(fn ($page) => $page
                ->component('Errors/Forbidden')
                ->where('message', __('messages.errors.module_disabled', ['module' => __('general.modules.hr')])));
        $this->getJson(route('payrolls.index'))
            ->assertForbidden()
            ->assertJsonFragment(['message' => __('messages.errors.module_disabled', ['module' => __('general.modules.hr')])]);

        // The rest of the app is untouched.
        $this->get(route('items.index'))->assertOk();
    }

    public function test_the_pages_are_told_which_paths_to_leave_out(): void
    {
        set_company_setting('modules.expenses', false, $this->ctx['company']);

        $this->get(route('items.index'))
            ->assertInertia(fn ($page) => $page
                ->where('modules.disabled_paths', ['/expenses', '/expense-categories'])
                ->where('modules.available', ['hr', 'owners', 'expenses', 'landed_costs', 'discount_rules']));
    }

    public function test_the_module_list_page_opens(): void
    {
        $this->get(route('modules.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Modules/Index'));
    }

    public function test_planned_modules_are_never_on(): void
    {
        set_company_setting('modules.pos', true, $this->ctx['company']);

        $this->assertFalse(Modules::isEnabled('pos', $this->ctx['company']->fresh()));
        $this->assertNotContains('pos', Modules::available());
    }

    public function test_a_business_type_sets_the_default(): void
    {
        $grocery = new Company(['business_type' => 'grocery']);
        $pharmacy = new Company(['business_type' => 'pharmacy']);

        $this->assertFalse(Settings::forCompany($grocery)['modules']['hr']);
        $this->assertTrue(Settings::forCompany($pharmacy)['modules']['hr']);
    }

    public function test_only_known_modules_can_be_saved(): void
    {
        $this->from(route('company.show'))
            ->put(route('company.settings.update'), ['modules' => ['hr' => false, 'casino' => true]])
            ->assertSessionHasErrors('modules');

        $this->from(route('company.show'))
            ->put(route('company.settings.update'), ['modules' => ['hr' => false]])
            ->assertSessionHasNoErrors();

        $this->assertFalse(Modules::isEnabled('hr', $this->ctx['company']->fresh()));
    }
}
