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

        Schema::create('items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name')->index();
            $table->string('code')->index();
            $table->string('generic_name')->nullable();
            $table->string('packing')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->ulid('unit_measure_id')->index();
            $table->ulid('brand_id')->nullable()->index();
            $table->ulid('category_id')->nullable()->index();
            $table->double('minimum_stock')->nullable();
            $table->double('maximum_stock')->nullable();
            $table->json('colors')->nullable()->default('[]');
            $table->ulid('size_id')->nullable()->index();
            $table->double('purchase_price')->nullable();
            $table->double('cost')->nullable();
            $table->double('sale_price');
            $table->double('rate_a')->nullable();
            $table->double('rate_b')->nullable();
            $table->double('rate_c')->nullable();
            $table->string('rack_no')->nullable();
            $table->string('fast_search')->nullable()->index();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->unique(['branch_id', 'name', 'deleted_at']);
            $table->unique(['branch_id', 'code', 'deleted_at']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('brand_id')->references('id')->on('brands');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('size_id')->references('id')->on('sizes');
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
        Schema::dropIfExists('items');
    }
};
