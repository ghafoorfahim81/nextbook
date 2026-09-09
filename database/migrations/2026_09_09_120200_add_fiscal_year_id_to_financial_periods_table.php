<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hang the existing periods table off a fiscal year.
 *
 * `financial_periods` shipped with one auto-provisioned row per branch covering
 * a whole year (BranchProvisioningService::financialPeriod). Nothing has ever
 * read that row — no query, report, guard or route referenced this table — so
 * those legacy rows are dropped here rather than migrated. Leaving them would
 * put a year-long period alongside the twelve monthly ones and make "which
 * period is this date in" ambiguous on day one.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Verified before writing this: no code path reads financial_periods,
        // so there is nothing downstream to invalidate.
        DB::table('financial_periods')->delete();

        Schema::table('financial_periods', function (Blueprint $table) {
            $table->ulid('fiscal_year_id')->nullable()->after('id')->index();
            $table->foreign('fiscal_year_id')->references('id')->on('fiscal_years');

            $table->index(['branch_id', 'start_date', 'end_date'], 'financial_periods_branch_range_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE financial_periods
            ADD CONSTRAINT chk_financial_period_range
            CHECK (end_date IS NULL OR end_date >= start_date)
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE financial_periods DROP CONSTRAINT IF EXISTS chk_financial_period_range');

        Schema::table('financial_periods', function (Blueprint $table) {
            $table->dropForeign(['fiscal_year_id']);
            $table->dropIndex('financial_periods_branch_range_idx');
            $table->dropColumn('fiscal_year_id');
        });
    }
};
