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
            $table->timestamps();
        });

        Schema::table('stock_openings', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('stock_id')->references('id')->on('stocks');
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
