<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * `status` (active / inactive / blocked) fully replaces the `is_active`
 * boolean on ledgers. The Ledger model still answers `$ledger->is_active`,
 * derived from the status, but nothing is stored under that name any more.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Anything switched off after the status column was added, by code
        // that only knew the boolean, must not come back as active.
        DB::statement("UPDATE ledgers SET status = 'inactive' WHERE is_active = false AND status = 'active'");

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        DB::statement("UPDATE ledgers SET is_active = (status = 'active')");
    }
};
