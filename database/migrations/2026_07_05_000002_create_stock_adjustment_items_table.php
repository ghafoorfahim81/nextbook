<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('stock_adjustment_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('stock_adjustment_id');
            $table->ulid('item_id');
            $table->ulid('unit_measure_id')->nullable()->index();
            $table->decimal('quantity', 18, 4);
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->string('batch')->nullable();
            $table->date('expire_date')->nullable();
            $table->ulid('size_id')->nullable()->index();
            $table->ulid('category_id')->nullable()->index();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->index(['branch_id', 'stock_adjustment_id']);
            $table->index(['branch_id', 'item_id']);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('stock_adjustment_items', function (Blueprint $table) {
            $table->foreign('stock_adjustment_id')->references('id')->on('stock_adjustments');
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustment_items');
    }
};
