<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Item transfers had no number, so their ledger entries carried no reference.
 * Numbered oldest first within each company.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_transfers', function (Blueprint $table) {
            $table->string('number')->nullable()->index()->after('company_id');
        });

        DB::statement("
            UPDATE item_transfers SET number = numbered.rn::text
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY created_at, id) AS rn
                FROM item_transfers
            ) numbered
            WHERE item_transfers.id = numbered.id
        ");

        // Ledger entries already posted for a transfer get its reference too.
        DB::statement("
            UPDATE transactions SET voucher_number = 'Item Transfer #' || t.number
            FROM item_transfers t
            WHERE transactions.reference_type IN ('item_transfer', 'App\Models\ItemTransfer\ItemTransfer')
              AND transactions.reference_id = t.id
              AND transactions.voucher_number IS NULL
        ");
    }

    public function down(): void
    {
        Schema::table('item_transfers', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
