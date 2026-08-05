<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Every transaction line gets variant / lot / piece.
 *
 * Six of these carry size_id today but no colour — stock_outs,
 * purchase_return_items, purchase_order_items, purchase_quotation_items,
 * sale_quotation_items and item_transfer_items — so a purchase order can
 * currently record "Large" but not "Red". They are deliberately not given a
 * colour column here: they go straight to variant_id instead.
 *
 * Nullable for now. variant_id becomes NOT NULL once every item has its
 * default variant and the backfill has run.
 */
return new class extends Migration
{
    private const TABLES = [
        'purchase_items',
        'purchase_return_items',
        'purchase_order_items',
        'purchase_quotation_items',
        'sale_items',
        'sale_return_items',
        'sale_order_items',
        'sale_quotation_items',
        'item_transfer_items',
        'stock_adjustment_items',
        'landed_cost_items',
        'stock_outs',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->ulid('variant_id')->nullable()->after('item_id')->index();
                $table->ulid('lot_id')->nullable()->after('variant_id')->index();
                $table->ulid('piece_id')->nullable()->after('lot_id')->index();
            });

            Schema::table($name, function (Blueprint $table) {
                $table->foreign('variant_id')->references('id')->on('item_variants');
                $table->foreign('lot_id')->references('id')->on('stock_lots');
                $table->foreign('piece_id')->references('id')->on('stock_pieces');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            if (! Schema::hasTable($name)) {
                continue;
            }

            Schema::table($name, function (Blueprint $table) {
                $table->dropForeign(['variant_id']);
                $table->dropForeign(['lot_id']);
                $table->dropForeign(['piece_id']);
                $table->dropColumn(['variant_id', 'lot_id', 'piece_id']);
            });
        }
    }
};
