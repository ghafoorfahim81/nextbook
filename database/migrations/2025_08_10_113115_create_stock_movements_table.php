<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\StockSourceType;
use App\Enums\StockDirectionType;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->ulid('id')->primary();

            $table->ulid('branch_id')->index();

            $table->ulid('item_id')->index();
            $table->ulid('warehouse_id')->index();
            $table->ulid('unit_measure_id')->index();
            $table->ulid('size_id')->nullable()->index();

            $table->enum('direction', StockDirectionType::values()); // IN / OUT
            $table->enum('source_type', StockSourceType::values());     // purchase, sale, adjustment, transfer...

            $table->string('batch')->nullable();
            $table->date('expire_date')->nullable();

            $table->date('date'); // NOT NULL

            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4);

            $table->nullableUlidMorphs('reference');

            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();

            $table->timestamps();

            // composite indexes
            $table->index(['branch_id', 'item_id', 'warehouse_id', 'date']);
            // $table->index(['reference_type', 'reference_id']);
            $table->index(['branch_id', 'item_id', 'warehouse_id', 'batch', 'expire_date']);
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('size_id')->references('id')->on('sizes');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_movements');
    }
};
