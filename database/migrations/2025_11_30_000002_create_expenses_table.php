<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->date('date');
            $table->text('remarks')->nullable();
            $table->ulid('category_id')->index(); 
            $table->string('attachment')->nullable();
            $table->ulid('expense_transaction_id')->nullable()->index();
            $table->ulid('bank_transaction_id')->nullable()->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('branch_id')->index();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::table('expenses', function (Blueprint $table) { 
            $table->foreign('category_id')->references('id')->on('expense_categories');
            $table->foreign('expense_transaction_id')->references('id')->on('transactions');
            $table->foreign('bank_transaction_id')->references('id')->on('transactions');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expenses');
    }
};

