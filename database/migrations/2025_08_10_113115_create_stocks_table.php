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
        Schema::create('stocks', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('item_id');
            $table->char('store_id', 26);
            $table->char('unit_measure_id', 26);
            $table->double('quantity');
            $table->double('unit_price')->unsigned();
            $table->double('free')->nullable();
            $table->string('batch')->nullable();
            $table->double('discount')->nullable();
            $table->double('tax')->nullable();
            $table->date('date')->nullable();
            $table->index(['item_id', 'store_id', 'batch']);
            $table->date('expire_date')->nullable();
            $table->nullableUlidMorphs('source'); // adds source_type, source_id (ULID), nullable
            $table->timestamps();
        });

        schema::table('stocks', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stocks');
    }
};
