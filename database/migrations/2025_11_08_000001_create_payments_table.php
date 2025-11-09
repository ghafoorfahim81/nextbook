<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('payments', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('number')->index();
            $table->date('date');
            $table->char('ledger_id', 26)->index();
            $table->char('payment_transaction_id', 26)->nullable();
            $table->char('bank_transaction_id', 26)->nullable();
            $table->string('cheque_no')->nullable();
            $table->text('description')->nullable();
            $table->char('branch_id', 26)->nullable()->index();
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->char('deleted_by', 26)->nullable();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('payments', function (Blueprint $table) {
            $table->foreign('ledger_id')->references('id')->on('ledgers');
            $table->foreign('payment_transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('bank_transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payments');
    }
};


