<?php

namespace Tests\Feature\System;

use App\Models\Administration\Category;
use App\Services\DeletedRecordService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class DeletedRecordsFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_it_lists_soft_deleted_records_from_registered_modules(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Archived Category',
        ]);

        $category->delete();

        $response = $this->get(route('deleted-records.index'));

        $response->assertOk();
        $response->assertInertia(function (AssertableInertia $page) use ($category) {
            $page->component('System/DeletedRecords/Index')
                ->where('records.data.0.module', 'categories')
                ->where('records.data.0.record_id', $category->id)
                ->where('records.data.0.title', 'Archived Category')
                ->where('records.data.0.deleted_by_name', $this->ctx['user']->name);
        });
    }

    public function test_it_restores_a_soft_deleted_record_from_the_central_module(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Restorable Category',
        ]);

        $category->delete();

        $response = $this->patch(route('deleted-records.restore', [
            'module' => 'categories',
            'record' => $category->id,
        ]));

        $response->assertRedirect();
        $this->assertNotSoftDeleted('categories', ['id' => $category->id]);
    }

    public function test_auto_cleanup_force_deletes_expired_deleted_records(): void
    {
        $category = Category::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'name' => 'Expired Category',
        ]);

        $category->delete();
        DB::table('categories')
            ->where('id', $category->id)
            ->update([
                'deleted_at' => now()->subDays(31),
                'updated_at' => now()->subDays(31),
            ]);

        $deleted = app(DeletedRecordService::class)->cleanupExpired();

        $this->assertSame(1, $deleted);
        $this->assertDatabaseMissing('categories', [
            'id' => $category->id,
        ]);
    }
}
