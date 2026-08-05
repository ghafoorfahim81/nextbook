<?php

use App\Enums\ItemCondition;
use App\Enums\PieceStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A piece is one physical object you can pick up.
 *
 * Named "pieces" rather than "units" to avoid colliding with unit-of-measure.
 * stock_balances says how many; this table says which ones.
 *
 * `identifiers` is JSONB rather than a serial_no column because a dual-SIM
 * phone needs two IMEIs, a vehicle needs a VIN and an engine number, and a
 * carpet needs neither.
 *
 * There is deliberately no sale_item_id. Indivisible pieces (a phone) are sold
 * once, but divisible ones (a 100m cable drum sold 5m at a time) are consumed
 * by many sales, which a single foreign key cannot express. Traceability comes
 * from stock_movements.piece_id instead.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_pieces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('item_id')->index();
            $table->ulid('variant_id')->index();
            $table->ulid('lot_id')->nullable()->index();
            $table->ulid('warehouse_id')->index();

            $table->jsonb('identifiers')->default('{}');
            $table->jsonb('attributes')->default('{}');
            // The sellable amount when a piece is measured rather than counted:
            // 6.3 m2 of carpet, 8.4 g of gold, 87.5 m remaining on a drum.
            $table->decimal('measured_quantity', 18, 4)->nullable();

            // Specific-identification costing: what THIS object cost.
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('sale_price', 18, 4)->nullable();
            $table->enum('condition', ItemCondition::values())->nullable();
            $table->enum('status', PieceStatus::values())->default(PieceStatus::IN_STOCK->value);

            $table->ulid('purchase_item_id')->nullable()->index();
            $table->date('warranty_end_date')->nullable();

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'item_id', 'status']);
            $table->index(['branch_id', 'warehouse_id', 'status']);
        });

        Schema::table('stock_pieces', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('variant_id')->references('id')->on('item_variants');
            $table->foreign('lot_id')->references('id')->on('stock_lots');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });

        DB::statement('CREATE INDEX stock_pieces_identifiers_gin
            ON stock_pieces USING GIN (identifiers)');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_pieces');
    }
};
