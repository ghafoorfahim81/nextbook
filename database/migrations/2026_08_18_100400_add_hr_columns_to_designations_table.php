<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
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

            $table->unique(['branch_id', 'code', 'deleted_at']);
        });

        Schema::table('designations', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('departments')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('designations', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique(['branch_id', 'code', 'deleted_at']);
            $table->dropColumn(['code', 'department_id', 'grade_level']);
        });
    }
};
