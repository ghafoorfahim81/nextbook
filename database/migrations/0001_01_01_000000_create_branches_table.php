<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A branch is an operating location of one company. `company_id` is not
 * nullable: a branch with no company is the "unknown scope" case the
 * architecture plan rejects outright, and allowing it would mean every
 * composite foreign key pointing here has a hole in it.
 *
 * The unique index on `name` is company-scoped. Two companies may both have a
 * "Kabul" branch; one company may not have two.
 *
 * `(company_id, id)` is redundant as a key — `id` alone is already unique —
 * but Postgres requires a unique index on exactly the referenced column list
 * before a composite foreign key can point at it. Every branch-scoped table
 * declares `FOREIGN KEY (company_id, branch_id) REFERENCES branches (company_id, id)`,
 * which is what makes a branch of company A unusable on a document of company
 * B at the database level rather than only in the application.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('company_id')->index();
            $table->string('name')->index();
            $table->text('remark')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_main')->default(false);
            $table->ulid('parent_id')->nullable()->index();
            $table->softDeletes();
            $table->timestamps();

            $table->unique(['company_id', 'id']);
        });

        // Not `unique(['company_id', 'name', 'deleted_at'])`. That pattern is
        // used across this schema and does not work in PostgreSQL: NULL != NULL,
        // and `deleted_at` is NULL on every live row, so the constraint never
        // fires and duplicates get in. A partial index over live rows is the
        // working form — the same fix
        // `2026_08_05_000007_fix_soft_delete_unique_constraints` applies to the
        // inventory tables.
        DB::statement('CREATE UNIQUE INDEX branches_company_name_live_unique
            ON branches (company_id, name) WHERE deleted_at IS NULL');

        Schema::table('branches', function (Blueprint $table) {
            $table->foreign('company_id')
                ->references('id')
                ->on('companies')
                ->cascadeOnDelete();

            // Composite rather than a plain `parent_id` reference so a sub-branch
            // cannot be parented to a branch of another company. `parent_id` is
            // nullable and Postgres skips the check when any referencing column
            // is NULL, so top-level branches are unaffected.
            $table->foreign(['company_id', 'parent_id'])
                ->references(['company_id', 'id'])
                ->on('branches')
                ->onDelete('CASCADE');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('branches');
    }
};
