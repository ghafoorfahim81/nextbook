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
            $table->double('qut_out')->unsigned();
            $table->double('sale_price')->unsigned();
            $table->double('free')->nullable();
            $table->double('tax')->nullable();
            $table->double('discount')->nullable();
            $table->date('date_out');
            $table->char('sale_id', 26)->nullable();
            $table->integer('sale_number')->unsigned()->nullable();
            $table->string('batch')->nullable();
            $table->char('unit_measure_id', 26)->nullable();
            $table->boolean('status')->default(false);
            $table->char('issue_id', 26)->nullable();
            $table->char('store_id', 26)->nullable();
            $table->char('created_by', 26)->nullable();
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
        });

        Schema::table('stock_outs', function (Blueprint $table) {
            $table->foreign('stock_id')->references('id')->on('stocks');
            $table->foreign('item_id')->references('id')->on('items');
            // $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('unit_measure_id')->references('id')->on('unit_measures');
            // $table->foreign('issue_id')->references('id')->on('issues');
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
        Schema::dropIfExists('stock_outs');
    }
};
