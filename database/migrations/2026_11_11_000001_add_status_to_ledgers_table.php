<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A party can be active, inactive or blocked — one more state than the
 * `is_active` boolean can hold.
 *
 * `status` is the column the screens read and write. `is_active` stays as its
 * mirror (true only when status is 'active'), kept in step by the Ledger
 * model: the ledgers table is shared with suppliers and employees, and every
 * picker in the system already filters on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('is_active');
        });

        DB::statement("UPDATE ledgers SET status = 'inactive' WHERE is_active = false");
        DB::statement("ALTER TABLE ledgers ADD CONSTRAINT ledgers_status_check CHECK (status IN ('active', 'inactive', 'blocked'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ledgers DROP CONSTRAINT IF EXISTS ledgers_status_check');

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
