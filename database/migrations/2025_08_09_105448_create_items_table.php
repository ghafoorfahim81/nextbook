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
            $table->char('id', 26)->primary();
            $table->string('name')->unique()->index();
            $table->string('code')->unique()->index();
            $table->string('generic_name')->nullable();
            $table->string('packing')->nullable();
            $table->string('barcode')->nullable()->index();
            $table->char('unit_measure_id',26);
            $table->char('company_id',26);
            $table->char('category_id',26);
            $table->double('minimum_stock')->nullable();
            $table->double('maximum_stock')->nullable();
            $table->string('colors')->nullable();
            $table->string('size')->nullable();
            $table->string('photo')->nullable();
            $table->double('purchase_price')->nullable();
            $table->double('cost')->nullable();
            $table->double('mrp_rate');
            $table->double('rate_a')->nullable();
            $table->double('rate_b')->nullable();
            $table->double('rate_c')->nullable();
            $table->integer('rack_no')->nullable();
            $table->string('fast_search')->nullable()->index();
            $table->char('branch_id',26);
            $table->text('description')->nullable();
            $table->char('created_by');
            $table->char('updated_by')->nullable();
            $table->timestamps();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            $table->foreign('company_id')->references('id')->on('companies');
            $table->foreign('category_id')->references('id')->on('categories');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
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
