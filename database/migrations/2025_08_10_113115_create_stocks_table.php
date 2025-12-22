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
            $table->char('item_id')->index();
            $table->char('store_id', 26)->index();
            $table->char('unit_measure_id', 26)->index();
            $table->double('quantity');
            $table->double('unit_price')->unsigned();
            $table->double('free')->nullable();
            $table->string('batch')->nullable();
            $table->double('discount')->nullable();
            $table->double('tax')->nullable();
            $table->date('date')->nullable(); 
            $table->date('expire_date')->nullable();
            $table->char('created_by')->index();
            $table->char('updated_by')->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->nullableUlidMorphs('source'); // adds source_type, source_id (ULID), nullable
            $table->timestamps();
            $table->softDeletes();
        });

        schema::table('stocks', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
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
