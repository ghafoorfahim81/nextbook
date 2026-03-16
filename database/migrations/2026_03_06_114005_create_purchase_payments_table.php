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

        Schema::create('purchase_payments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('purchase_id');
            $table->ulid('payment_id'); 
            $table->ulid('created_by');
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('branch_id');
            $table->index(['branch_id', 'purchase_id']);
            $table->index(['branch_id', 'payment_id']);
            $table->index(['branch_id', 'created_by']);            
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::enableForeignKeyConstraints();
        Schema::table('purchase_payments', function (Blueprint $table) {
            $table->foreign('purchase_id')->references('id')->on('purchases')->onDelete('cascade');
            $table->foreign('payment_id')->references('id')->on('payments')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchase_payments');
    }
};
