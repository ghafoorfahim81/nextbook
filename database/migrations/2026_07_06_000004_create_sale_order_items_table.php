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

        Schema::create('sale_order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('sale_order_id');
            $table->ulid('item_id');
            $table->decimal('quantity', 10, 2);
            $table->decimal('free', 10, 2)->nullable()->default(0);
            $table->decimal('unit_price', 18, 4);
            $table->ulid('unit_measure_id')->nullable()->index();
            $table->string('batch')->nullable();
            $table->date('expire_date')->nullable();
            $table->ulid('size_id')->nullable()->index();
            $table->ulid('category_id')->nullable()->index();
            $table->decimal('discount', 10, 2)->nullable()->default(0);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->index(['branch_id', 'sale_order_id']);
            $table->index(['branch_id', 'item_id']);
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('sale_order_items', function (Blueprint $table) {
            $table->foreign('sale_order_id')->references('id')->on('sale_orders');
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('size_id')->references('id')->on('sizes');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_order_items');
    }
};
