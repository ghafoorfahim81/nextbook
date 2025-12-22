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
        Schema::create('item_opening_transactions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('item_id', 26)->index(); 
            $table->char('inventory_transaction_id', 26)->index();
            $table->char('opening_balance_transaction_id', 26)->index();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('item_opening_transactions', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('inventory_transaction_id')->references('id')->on('transactions');
            $table->foreign('opening_balance_transaction_id')->references('id')->on('transactions');
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
        Schema::dropIfExists('item_opening_transactions');
    }
};
