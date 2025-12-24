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
            $table->ulid('id')->primary();
            $table->ulid('stock_id')->nullable()->index();
            $table->ulid('item_id')->index();
            $table->double('quantity')->unsigned();
            $table->double('unit_price')->unsigned();
            $table->double('free')->nullable();
            $table->double('tax')->nullable();
            $table->double('discount')->nullable();
            $table->date('date');
            $table->string('batch')->nullable();
            $table->ulid('unit_measure_id')->index();
            $table->ulid('size_id')->nullable()->index();
            $table->ulid('store_id')->index();
            $table->nullableUlidMorphs('source'); // adds source_type, source_id (ULID), nullable
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->ulid('deleted_by')->nullable();
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
