<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Orders and quotations never used the line category for anything: the item
 * already has one. The column, its index and its foreign key go together.
 */
return new class extends Migration
{
    private const TABLES = [
        'sale_order_items',
        'sale_quotation_items',
        'purchase_order_items',
        'purchase_quotation_items',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} DROP COLUMN IF EXISTS category_id");
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            DB::statement("ALTER TABLE {$table} ADD COLUMN IF NOT EXISTS category_id char(26) NULL REFERENCES categories (id)");
            DB::statement("CREATE INDEX IF NOT EXISTS {$table}_category_id_index ON {$table} (category_id)");
        }
    }
};
