<?php

use App\Models\Administration\Category;
use App\Models\Administration\LandedCostCategory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->string('local_name')->nullable()->after('name');
        });

        Schema::table('landed_cost_categories', function (Blueprint $table) {
            $table->string('local_name')->nullable()->after('name');
        });

        // Backfill the localized name for the categories the app ships with, so
        // existing branches pick them up without a re-seed.
        $this->backfill('categories', Category::defaultCategories());
        $this->backfill('landed_cost_categories', LandedCostCategory::defaultCategories());
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('local_name');
        });

        Schema::table('landed_cost_categories', function (Blueprint $table) {
            $table->dropColumn('local_name');
        });
    }

    private function backfill(string $table, array $definitions): void
    {
        foreach ($definitions as $definition) {
            if (empty($definition['local_name'])) {
                continue;
            }

            DB::table($table)
                ->where('name', $definition['name'])
                ->whereNull('local_name')
                ->update(['local_name' => $definition['local_name']]);
        }
    }
};
