<?php

namespace Tests\Feature\Preferences;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class SidebarMenuPreferencesTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_hiding_menus_is_honoured_exactly_and_not_repopulated_from_defaults(): void
    {
        /** @var User $user */
        $user = $this->ctx['user'];

        $this->from(route('preferences.index'))
            ->put(route('preferences.update'), [
                'appearance' => [
                    'sidebar_menus' => ['dashboard', 'sale', 'reports'],
                ],
            ])
            ->assertRedirect();

        $menus = $user->fresh()->getAllPreferences()['appearance']['sidebar_menus'];

        // The stored subset must survive verbatim - no leftover default tail.
        sort($menus);
        $this->assertSame(['dashboard', 'reports', 'sale'], $menus);
    }

    public function test_legacy_menu_keys_are_folded_into_current_ones(): void
    {
        /** @var User $user */
        $user = $this->ctx['user'];
        $user->forceFill([
            'preferences' => [
                'appearance' => [
                    'sidebar_menus' => ['dashboard', 'receipt', 'payment', 'transfer', 'preferences', 'sale'],
                ],
            ],
        ])->saveQuietly();

        $menus = $user->fresh()->getAllPreferences()['appearance']['sidebar_menus'];

        $this->assertContains('cash_transactions', $menus);
        $this->assertNotContains('receipt', $menus);
        $this->assertNotContains('payment', $menus);
        $this->assertNotContains('transfer', $menus);
        // Settings live in the user menu now, not in the sidebar's list.
        $this->assertNotContains('preferences', $menus);
    }
}
