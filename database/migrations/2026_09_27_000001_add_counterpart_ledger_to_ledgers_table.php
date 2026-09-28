<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The other account belonging to the same person.
 *
 * Someone who both buys from us and sells to us has two ledgers, because a
 * ledger's control account follows its type and one balance cannot be both a
 * receivable and a payable. Until now nothing in the data said the two were
 * the same person: their statements read as two strangers, and whoever posted
 * the set-off between them had to remember the pairing.
 *
 * The link is SYMMETRIC — both rows point at each other — so either side can
 * reach the other without a union query, and a list can show the pairing
 * without a second lookup. LedgerLinkService owns writing it; nothing else
 * should set this column directly, or the two halves drift apart.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->ulid('counterpart_ledger_id')->nullable()->after('type')->index();
        });

        Schema::table('ledgers', function (Blueprint $table) {
            // Nulled rather than cascaded: deleting one account of a pair must
            // never take the other's history with it.
            $table->foreign('counterpart_ledger_id')
                ->references('id')
                ->on('ledgers')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropForeign(['counterpart_ledger_id']);
            $table->dropColumn('counterpart_ledger_id');
        });
    }
};
