<?php

use App\Enums\FinancialPeriodStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A financial year, and the parent of the twelve periods inside it.
 *
 * `financial_periods` already existed and already carried start/end/status, so
 * the months keep living there; this table is what they hang off. Two levels
 * rather than one because closing is wanted at both: a month is closed once its
 * bank reconciliation is signed off, while the year is closed once — at which
 * point every month inside it is closed too and stays that way.
 *
 * Dates are GREGORIAN, like every other date column in the schema. Controllers
 * convert Jalali input at the edge (DateConversionService::toGregorian), so the
 * posting guard compares Gregorian to Gregorian and the Jalali calendar stays a
 * presentation concern. `name` is what carries the local reading of the year —
 * "1404" for an Afghan company, "2026" for a Gregorian one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('fiscal_years', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', FinancialPeriodStatus::values())
                ->default(FinancialPeriodStatus::Open->value);
            $table->timestamp('closed_at')->nullable();
            $table->ulid('closed_by')->nullable()->index();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // The guard resolves a period from a date on every single post, so
            // the range lookup has to be indexed.
            $table->index(['branch_id', 'start_date', 'end_date'], 'fiscal_years_branch_range_idx');
        });

        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('closed_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        \Illuminate\Support\Facades\DB::statement(<<<'SQL'
            ALTER TABLE fiscal_years ADD CONSTRAINT chk_fiscal_year_range CHECK (end_date >= start_date)
        SQL);

        \Illuminate\Support\Facades\DB::statement(<<<'SQL'
            CREATE UNIQUE INDEX fiscal_years_branch_name_unique
            ON fiscal_years (branch_id, name)
            WHERE deleted_at IS NULL
        SQL);

        // Two years covering the same day make "which period is this date in"
        // ambiguous, and the guard would answer it arbitrarily — which is
        // exactly what happened when a company's fiscal-year start was changed
        // after some years had already been generated. An overlap is a data bug
        // the database can refuse outright, so it does.
        //
        // Best effort: the exclusion constraint needs btree_gist, and creating
        // an extension needs rights the app user may not have on a managed
        // Postgres. FiscalYearService::generate() performs the same check in
        // PHP and raises a readable error, so an install without the extension
        // is protected — just later, and without the backstop.
        try {
            \Illuminate\Support\Facades\DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            \Illuminate\Support\Facades\DB::statement(<<<'SQL'
                ALTER TABLE fiscal_years
                ADD CONSTRAINT fiscal_years_no_overlap
                EXCLUDE USING gist (
                    branch_id WITH =,
                    daterange(start_date, end_date, '[]') WITH &&
                ) WHERE (deleted_at IS NULL)
            SQL);
        } catch (\Throwable $e) {
            logger()->warning(
                'fiscal_years overlap constraint not installed; relying on the application check. '
                . $e->getMessage()
            );
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('fiscal_years');
    }
};
