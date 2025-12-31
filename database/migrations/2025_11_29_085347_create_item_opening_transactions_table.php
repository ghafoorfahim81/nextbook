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
            $table->ulid('id')->primary();
            $table->ulid('item_id')->index(); 
            $table->ulid('inventory_transaction_id')->index();
            $table->ulid('opening_balance_transaction_id')->index();
            $table->ulid('branch_id')->index(); 
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('item_opening_transactions', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('inventory_transaction_id')->references('id')->on('transactions');
            $table->foreign('opening_balance_transaction_id')->references('id')->on('transactions');
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
        Schema::dropIfExists('item_opening_transactions');
    }
};
