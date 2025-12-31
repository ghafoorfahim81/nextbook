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
            $table->ulid('id')->primary();
            $table->ulid('item_id')->index();
            $table->ulid('store_id')->index();
            $table->ulid('unit_measure_id')->index();
            $table->double('quantity');
            $table->double('unit_price')->unsigned();
            $table->double('free')->nullable();
            $table->ulid('size_id')->nullable()->index();
            $table->string('batch')->nullable();
            $table->double('discount')->nullable();
            $table->double('tax')->nullable();
            $table->date('date')->nullable();
            $table->date('expire_date')->nullable();
            $table->ulid('branch_id')->index(); 
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->nullableUlidMorphs('source'); // adds source_type, source_id (ULID), nullable
            $table->timestamps();
            $table->softDeletes();
        });

        schema::table('stocks', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('store_id')->references('id')->on('stores');
            $table->foreign('branch_id')->references('id')->on('branches');
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
