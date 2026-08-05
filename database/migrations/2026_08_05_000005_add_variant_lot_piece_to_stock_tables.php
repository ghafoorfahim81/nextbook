<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Point the stock ledger at the new identity tables.
 *
 * The ledger must never know what a "colour" is — the moment it does, it
 * belongs to the clothing business and every other trade needs a new column.
 * variant_id is opaque: a garment shop resolves it to size/colour, a computer
 * shop to RAM/storage, and the ledger cannot tell the difference.
 *
 * The legacy batch / expire_date / color / size_id columns stay for now so
 * nothing breaks mid-migration; they are dropped once the backfill has run and
 * been verified.
 *
 * stock_balances deliberately gets no piece_id. It is an aggregate, and a piece
 * is always quantity 1 — adding it would turn 500 phones into 500 balance rows
 * duplicating the 500 piece rows. Movements get piece_id because a movement is
 * an event affecting one specific object.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->ulid('variant_id')->nullable()->after('item_id')->index();
            $table->ulid('lot_id')->nullable()->after('variant_id')->index();
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->foreign('variant_id')->references('id')->on('item_variants');
            $table->foreign('lot_id')->references('id')->on('stock_lots');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->ulid('variant_id')->nullable()->after('item_id')->index();
            $table->ulid('lot_id')->nullable()->after('variant_id')->index();
            $table->ulid('piece_id')->nullable()->after('lot_id')->index();
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('variant_id')->references('id')->on('item_variants');
            $table->foreign('lot_id')->references('id')->on('stock_lots');
            $table->foreign('piece_id')->references('id')->on('stock_pieces');
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropForeign(['lot_id']);
            $table->dropColumn(['variant_id', 'lot_id']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropForeign(['variant_id']);
            $table->dropForeign(['lot_id']);
            $table->dropForeign(['piece_id']);
            $table->dropColumn(['variant_id', 'lot_id', 'piece_id']);
        });
    }
};
