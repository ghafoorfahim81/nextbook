<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Per-unit stock costs go from 4 to 8 decimal places, keeping the whole-number
 * digits they had. See App\Casts\StockCost for why. This only stops new drift:
 * figures already rounded to 4 places stay as they are.
 */
return new class extends Migration
{
    private const COLUMNS = [
        'stock_movements' => ['unit_cost', 18],
        'items' => ['avg_cost', 18],
        'item_variants' => ['avg_cost', 18],
        'stock_lots' => ['unit_cost', 18],
        'stock_pieces' => ['unit_cost', 18],
        'stock_adjustment_items' => ['unit_cost', 18],
        'purchase_items' => ['net_unit_cost', 18],
        'sale_items' => ['net_unit_cost', 18],
        'sale_return_items' => ['net_unit_cost', 18],
        'landed_cost_items' => ['unit_cost', 12],
    ];

    public function up(): void
    {
        foreach (self::COLUMNS as $table => [$column, $precision]) {
            $widened = $precision + 4;

            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE numeric({$widened}, 8)");
        }
    }

    public function down(): void
    {
        foreach (self::COLUMNS as $table => [$column, $precision]) {
            DB::statement("ALTER TABLE {$table} ALTER COLUMN {$column} TYPE numeric({$precision}, 4)");
        }
    }
};
