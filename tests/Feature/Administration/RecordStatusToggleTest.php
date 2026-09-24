<?php

namespace Tests\Feature\Administration;

use App\Enums\UserStatus;
use App\Models\Administration\Category;
use App\Models\Administration\Warehouse;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Turning a record off without deleting it.
 *
 * Categories, unit measures, warehouses, discount rules and users all carry a
 * status, but nothing in the UI ever changed it — and the delete guard refuses
 * to remove anything that other rows reference, so a warehouse you stopped
 * using had no way out of the pickers at all.
 */
class RecordStatusToggleTest extends TestCase
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

    public function test_a_warehouse_can_be_deactivated_and_switched_back_on(): void
    {
        $warehouse = Warehouse::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $this->patch(route('warehouses.toggle-status', $warehouse))
            ->assertRedirect();

        $this->assertFalse((bool) $warehouse->fresh()->is_active);

        $this->patch(route('warehouses.toggle-status', $warehouse))
            ->assertRedirect();

        $this->assertTrue((bool) $warehouse->fresh()->is_active);
    }

    public function test_a_category_can_be_deactivated(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
        ]);

        $this->patch(route('categories.toggle-status', $category))->assertRedirect();

        $this->assertFalse((bool) $category->fresh()->is_active);
    }

    /** Users carry only the enum — there is no is_active column on that table. */
    public function test_deactivating_a_user_moves_the_status_enum_too(): void
    {
        $user = User::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'company_id' => $this->ctx['company']->id,
            'status' => UserStatus::ACTIVE->value,
        ]);

        $this->patch(route('users.toggle-status', $user))->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame(UserStatus::INACTIVE->value, $fresh->status instanceof UserStatus ? $fresh->status->value : $fresh->status);
    }

    /**
     * Blocked is a different decision from inactive — reactivating an account
     * must not quietly unblock it.
     */
    public function test_a_blocked_user_stays_blocked(): void
    {
        $user = User::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'company_id' => $this->ctx['company']->id,
            'status' => UserStatus::BLOCKED->value,
        ]);

        $this->patch(route('users.toggle-status', $user))->assertRedirect();

        $fresh = $user->fresh();
        $this->assertSame(UserStatus::BLOCKED->value, $fresh->status instanceof UserStatus ? $fresh->status->value : $fresh->status);
    }
}
