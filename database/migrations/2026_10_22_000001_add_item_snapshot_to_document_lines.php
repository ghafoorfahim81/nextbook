<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Keep what each document line said about its item at the time it was written.
 *
 * Phase 2 made the catalog shared by every branch of a company, so one rename
 * now reaches every document that ever named the item. The lines stored only
 * item_id, variant_id and unit_measure_id and read the name, code, variant and
 * unit back live, which meant renaming an item rewrote how past invoices read.
 * These columns hold the values as written; the resources prefer them and fall
 * back to the live record only for a line that has none.
 *
 * Only the documents a customer or supplier sees are covered. Stock
 * adjustments, transfers, landed costs and openings are internal and never
 * printed for anyone outside the business.
 *
 * Added as a new migration, not by editing the create migrations in place, so
 * a database that is already migrated picks the columns up and has its
 * existing lines filled from their current values.
 */
return new class extends Migration
{
    private const TABLES = [
        'sale_items',
        'sale_order_items',
        'sale_quotation_items',
        'sale_return_items',
        'purchase_items',
        'purchase_order_items',
        'purchase_quotation_items',
        'purchase_return_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->string('item_name')->nullable();
                $blueprint->string('item_code')->nullable();
                $blueprint->string('variant_name')->nullable();
                $blueprint->string('unit_name')->nullable();
                $blueprint->string('unit_symbol')->nullable();
            });

            $this->backfill($table);
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            Schema::table($table, function (Blueprint $blueprint) {
                $blueprint->dropColumn(['item_name', 'item_code', 'variant_name', 'unit_name', 'unit_symbol']);
            });
        }
    }

    /**
     * Fill existing lines from the records they point at today — the closest
     * thing to the values they were written with that still exists.
     *
     * Soft-deleted items, variants and units are included: a line keeps naming
     * what it sold even after the item is retired.
     */
    private function backfill(string $table): void
    {
        DB::statement("
            UPDATE {$table} AS line
            SET item_name = i.name, item_code = i.code
            FROM items AS i
            WHERE i.id = line.item_id
        ");

        DB::statement("
            UPDATE {$table} AS line
            SET unit_name = u.name, unit_symbol = u.symbol
            FROM unit_measures AS u
            WHERE u.id = line.unit_measure_id
        ");

        // The same rule as ItemVariant::displayName(): its own name, else its
        // non-empty attribute values joined with ' / ', else the item's name.
        DB::statement("
            UPDATE {$table} AS line
            SET variant_name = COALESCE(
                NULLIF(v.name, ''),
                (
                    SELECT string_agg(a.value, ' / ' ORDER BY a.ord)
                    FROM jsonb_each_text(
                        CASE WHEN jsonb_typeof(v.attributes) = 'object' THEN v.attributes ELSE '{}'::jsonb END
                    ) WITH ORDINALITY AS a(key, value, ord)
                    WHERE a.value IS NOT NULL AND a.value NOT IN ('', '0')
                ),
                i.name
            )
            FROM item_variants AS v
            JOIN items AS i ON i.id = v.item_id
            WHERE v.id = line.variant_id
        ");
    }
};
