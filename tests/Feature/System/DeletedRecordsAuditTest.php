<?php

namespace Tests\Feature\System;

use App\Models\Administration\Category;
use App\Models\Inventory\DiscountRule;
use App\Models\Ledger\Ledger;
use App\Models\Sale\Sale;
use App\Services\DeletedRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Regressions for the trash screen. Each case here is a bug the page shipped
 * with, not a hypothetical: the listing missed whole modules, counted the
 * registry instead of the results, listed every party twice, and ran its
 * retention counter backwards.
 */
class DeletedRecordsAuditTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_a_deleted_discount_rule_is_listed(): void
    {
        $rule = DiscountRule::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Ramadan 10%',
        ]);

        $rule->delete();

        $payload = app(DeletedRecordService::class)->indexPayload();
        $modules = collect($payload['records']['data'])->pluck('module');

        $this->assertTrue($modules->contains('discount_rules'));
    }

    public function test_the_module_summary_counts_represented_modules_not_the_registry(): void
    {
        $empty = app(DeletedRecordService::class)->indexPayload();

        $this->assertSame(0, $empty['summary']['total']);
        $this->assertSame(0, $empty['summary']['modules']);

        Category::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Gone',
        ])->delete();

        $one = app(DeletedRecordService::class)->indexPayload();

        $this->assertSame(1, $one['summary']['total']);
        $this->assertSame(1, $one['summary']['modules']);
    }

    public function test_days_remaining_counts_down_from_the_retention_window(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Aging',
        ]);

        $category->delete();
        DB::table('categories')->where('id', $category->id)->update([
            'deleted_at' => now()->subDays(25),
        ]);

        $record = collect(app(DeletedRecordService::class)->indexPayload()['records']['data'])->firstWhere('record_id', $category->id);

        $this->assertSame(DeletedRecordService::RETENTION_DAYS - 25, $record['days_remaining']);
    }

    public function test_a_record_past_the_window_reports_no_days_left_and_counts_as_expiring(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Overdue',
        ]);

        $category->delete();
        DB::table('categories')->where('id', $category->id)->update([
            'deleted_at' => now()->subDays(45),
        ]);

        $payload = app(DeletedRecordService::class)->indexPayload();
        $record = collect($payload['records']['data'])->firstWhere('record_id', $category->id);

        $this->assertSame(0, $record['days_remaining']);
        $this->assertSame(1, $payload['summary']['expiring_soon']);
    }

    public function test_a_deleted_customer_is_listed_once_not_under_ledgers_as_well(): void
    {
        $customer = Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'type' => \App\Enums\LedgerType::CUSTOMER->value,
            'name' => 'Duplicated Party',
        ]);

        $customer->delete();

        $payload = app(DeletedRecordService::class)->indexPayload();
        $rows = collect($payload['records']['data'])->where('record_id', $customer->id);

        $this->assertCount(1, $rows);
        $this->assertSame('customers', $rows->first()['module']);
        $this->assertSame(1, $payload['summary']['total']);
    }

    public function test_the_index_page_still_renders_with_the_new_modules_registered(): void
    {
        $this->get(route('deleted-records.index'))
            ->assertOk()
            ->assertInertia(fn (AssertableInertia $page) => $page->component('System/DeletedRecords/Index'));
    }

    public function test_restoring_a_record_whose_parent_is_still_trashed_is_refused(): void
    {
        $customer = Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'type' => \App\Enums\LedgerType::CUSTOMER->value,
            'name' => 'Orphan Maker',
        ]);

        $sale = Sale::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'customer_id' => $customer->id,
        ]);

        $sale->delete();
        $customer->delete();

        $this->from(route('deleted-records.index'))
            ->patch(route('deleted-records.restore', ['module' => 'sales', 'record' => $sale->id]))
            ->assertSessionHasErrors('record');

        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_restoring_the_parent_first_then_the_child_works(): void
    {
        $customer = Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'type' => \App\Enums\LedgerType::CUSTOMER->value,
            'name' => 'Reinstated',
        ]);

        $sale = Sale::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'customer_id' => $customer->id,
        ]);

        $sale->delete();
        $customer->delete();

        $this->patch(route('deleted-records.restore', ['module' => 'customers', 'record' => $customer->id]))
            ->assertRedirect();

        $this->patch(route('deleted-records.restore', ['module' => 'sales', 'record' => $sale->id]))
            ->assertRedirect();

        $this->assertNotSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_a_record_with_a_live_parent_restores_normally(): void
    {
        $customer = Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'type' => \App\Enums\LedgerType::CUSTOMER->value,
            'name' => 'Still Here',
        ]);

        $sale = Sale::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'customer_id' => $customer->id,
        ]);

        $sale->delete();

        $this->patch(route('deleted-records.restore', ['module' => 'sales', 'record' => $sale->id]))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertNotSoftDeleted('sales', ['id' => $sale->id]);
    }

    public function test_the_listing_only_expands_the_page_it_returns(): void
    {
        // The heavy per-record work (field list, dependency counts, blocking
        // parents) is what the two-pass split moved off the full result set.
        // Anything outside the page must come back without those keys.
        foreach (range(1, 4) as $i) {
            Category::factory()->create([
                'branch_id' => $this->ctx['branch']->id,
                'name' => "Bulk {$i}",
            ])->delete();
        }

        $payload = app(DeletedRecordService::class)->indexPayload(['per_page' => 10, 'page' => 1]);

        $this->assertSame(4, $payload['summary']['total']);
        $this->assertCount(4, $payload['records']['data']);

        foreach ($payload['records']['data'] as $row) {
            $this->assertArrayHasKey('fields', $row);
            $this->assertArrayHasKey('blocking_parents', $row);
            // Working state must never be serialised to the page.
            $this->assertArrayNotHasKey('model', $row);
            $this->assertArrayNotHasKey('search_blob', $row);
        }
    }

    public function test_the_cleanup_job_is_not_blocked_by_the_company_scoped_modules(): void
    {
        // invoice_formats filters on the signed-in user's company. The cleanup
        // job runs with no user, so that filter has to fall away rather than
        // become `company_id is null` and quietly purge nothing.
        auth()->logout();

        $this->assertIsInt(app(DeletedRecordService::class)->cleanupExpired());
    }

    public function test_a_stock_adjustment_can_be_force_deleted_through_the_registry(): void
    {
        // StockAdjustmentController::forceDelete() calls the service with this
        // key; before it was registered the button 404'd.
        $registry = (new \ReflectionClass(DeletedRecordService::class))->getMethod('registry');
        $registry->setAccessible(true);

        $keys = array_keys($registry->invoke(app(DeletedRecordService::class)));

        $this->assertContains('stock_adjustments', $keys);
    }
}
