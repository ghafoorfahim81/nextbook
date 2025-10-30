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

        Schema::create('sale_items', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('sale_id', 26)->index();
            $table->char('item_id', 26)->index();
            $table->string('batch')->nullable();
            $table->date('expire_date')->nullable();
            $table->decimal('quantity', 10, 2);
            $table->char('unit_measure_id', 26)->nullable();
            $table->decimal('unit_price', 10, 2);
            $table->decimal('discount', 10, 2)->default(0)->nullable();
            $table->decimal('free', 10, 2)->default(0)->nullable();
            $table->decimal('tax', 10, 2)->default(0)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('sale_items', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sale_items');
    }
};
