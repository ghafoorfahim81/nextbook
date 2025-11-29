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
        Schema::create('stock_openings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('item_id');
            $table->char('stock_id',26);
            $table->char('created_by',26);
            $table->char('updated_by',26)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->char('deleted_by',26)->nullable();
        });

        Schema::table('stock_openings', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('stock_id')->references('id')->on('stocks');
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
        Schema::dropIfExists('stock_openings');
    }
};
