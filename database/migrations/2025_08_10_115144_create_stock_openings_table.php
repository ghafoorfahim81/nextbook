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
            $table->ulid('id')->primary();
            $table->ulid('item_id')->index();
            $table->ulid('stock_id')->index();
            $table->ulid('branch_id')->index(); 
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->ulid('deleted_by')->nullable();
        });

        Schema::table('stock_openings', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('stock_id')->references('id')->on('stocks');
            $table->foreign('branch_id')->references('id')->on('branches');
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
