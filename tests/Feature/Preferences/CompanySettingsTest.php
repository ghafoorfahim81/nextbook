<?php

namespace Tests\Feature\Preferences;

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Support\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Symfony\Component\Uid\Ulid;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Phase 6: a setting belongs either to the company or to the user
 * (config/preferences.php). The company's are the same for everyone in it and
 * are changed on the company page; Preferences holds the personal ones only.
 */
class CompanySettingsTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_a_company_key_comes_from_the_company_not_the_users_copy(): void
    {
        $this->actingAs($this->ctx['user']);
        $this->ctx['user']->setPreference('sale.invoice_prefix', 'MINE-')->save();

        $this->assertSame('INV-', user_preference('sale.invoice_prefix'));

        set_company_setting('sale.invoice_prefix', 'NB-');

        $this->assertSame('NB-', user_preference('sale.invoice_prefix'));
    }

    public function test_the_company_page_saves_settings_for_everyone(): void
    {
        $this->from(route('company.show'))
            ->put(route('company.settings.update'), [
                'sale' => ['invoice_prefix' => 'NB-'],
                'transaction' => ['sale_post_immediately' => false],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('company.show'));

        $colleague = $this->userWith(['preferences.update']);
        $settings = Settings::resolve($colleague, $this->ctx['company']->fresh());

        $this->assertSame('NB-', $settings['sale']['invoice_prefix']);
        $this->assertFalse($settings['transaction']['sale_post_immediately']);
    }

    public function test_the_company_page_shows_its_settings(): void
    {
        set_company_setting('sale.invoice_prefix', 'NB-', $this->ctx['company']);

        $this->get(route('companies.show', $this->ctx['company']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Company/Show')
                ->where('settings.sale.invoice_prefix', 'NB-'));
    }

    public function test_changing_company_settings_needs_the_permission(): void
    {
        $clerk = $this->userWith(['preferences.update', 'companies.update']);

        $this->actingAs($clerk)
            ->put(route('company.settings.update'), ['sale' => ['invoice_prefix' => 'HACK-']])
            ->assertForbidden();

        $this->assertNull(data_get($this->ctx['company']->fresh()->preferences, 'sale.invoice_prefix'));
    }

    public function test_company_settings_are_validated(): void
    {
        $this->from(route('company.show'))
            ->put(route('company.settings.update'), ['sale' => ['start_number' => 0]])
            ->assertSessionHasErrors('sale.start_number');
    }

    public function test_preferences_save_only_personal_settings(): void
    {
        $user = $this->ctx['user'];

        // A form panel sends its whole group back, company keys included.
        $this->from(route('preferences.index'))
            ->put(route('preferences.update'), [
                'sale' => ['invoice_prefix' => 'NB-', 'item_columns' => ['brand' => false]],
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $this->assertNull(data_get($this->ctx['company']->fresh()->preferences, 'sale.invoice_prefix'));
        $this->assertNotSame('NB-', data_get($user->fresh()->preferences, 'sale.invoice_prefix'));
        $this->assertFalse(data_get($user->fresh()->preferences, 'sale.item_columns.brand'));
    }

    private function userWith(array $permissions): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => 'limited-settings', 'guard_name' => 'web'],
            ['slug' => 'limited-settings'],
        );

        foreach ($permissions as $name) {
            $role->givePermissionTo(Permission::query()->firstOrCreate(
                ['name' => $name, 'guard_name' => 'web'],
                ['id' => (string) new Ulid()],
            ));
        }

        return tap(User::factory()->create([
            'preferences' => Settings::defaults(),
            'company_id' => $this->ctx['company']->id,
            'branch_id' => $this->ctx['branch']->id,
        ]), fn (User $user) => $user->assignRole($role));
    }
}
