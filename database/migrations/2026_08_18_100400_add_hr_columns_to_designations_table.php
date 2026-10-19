<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Designations predate HR and carry only a name and a remark. Employees need to
 * reference them by code, group them under a department and rank them by grade.
 *
 * Purely additive — the existing table shape and its data are untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->string('code')->nullable()->index()->after('name');
            $table->ulid('department_id')->nullable()->index()->after('code');
            $table->integer('grade_level')->nullable()->after('department_id');

        });

        // Partial: unique([..., 'deleted_at']) never fires in PostgreSQL,
        // because deleted_at is NULL on every live row and NULL != NULL.
        DB::statement('CREATE UNIQUE INDEX designations_company_id_code_live_unique ON designations (company_id, code) WHERE deleted_at IS NULL AND code IS NOT NULL');

        Schema::table('designations', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS designations_company_id_code_live_unique');

        Schema::table('designations', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn(['code', 'department_id', 'grade_level']);
        });
    }
};
