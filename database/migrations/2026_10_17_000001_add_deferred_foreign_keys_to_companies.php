<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Adds the two foreign keys the companies table could not declare at creation
 * time.
 *
 * `companies` is the root of the organisational tree and is created before
 * everything else, but two of its columns point at tables that are themselves
 * scoped to a company:
 *
 *     companies.currency_id -> currencies  (company-scoped)
 *     companies.created_by  -> users       (branch-scoped)
 *
 * Declaring these at creation time would need foreign key checks disabled,
 * which hides the cycle rather than breaking it. Adding them here — once both
 * tables exist — makes the dependency explicit and keeps the constraints real.
 *
 * `currency_id` stays nullable: a company row exists before its currency set
 * is provisioned.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
            $table->foreign('created_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['deleted_by']);
        });
    }
};
