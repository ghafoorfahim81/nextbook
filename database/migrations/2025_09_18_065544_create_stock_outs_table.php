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
        Schema::create('stock_outs', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('stock_id', 26)->nullable();
            $table->char('item_id', 26);
            $table->double('quantity')->unsigned();
            $table->double('unit_price')->unsigned();
            $table->double('free')->nullable();
            $table->double('tax')->nullable();
            $table->double('discount')->nullable();
            $table->date('date');
            $table->string('batch')->nullable();
            $table->char('unit_measure_id', 26)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->nullableUlidMorphs('source'); // adds source_type, source_id (ULID), nullable
            $table->index(['item_id', 'store_id', 'batch']);
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->char('deleted_by',26)->nullable();
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->foreign('stock_id')->references('id')->on('stocks');
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('store_id')->references('id')->on('stores');
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
        Schema::dropIfExists('stock_outs');
    }
};
