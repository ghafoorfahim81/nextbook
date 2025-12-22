<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expenses', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->date('date');
            $table->text('remarks')->nullable();
            $table->char('category_id', 26)->index(); 
            $table->string('attachment')->nullable();
            $table->char('expense_transaction_id', 26)->nullable()->index();
            $table->char('bank_transaction_id', 26)->nullable()->index();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->char('branch_id', 26)->index();
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

