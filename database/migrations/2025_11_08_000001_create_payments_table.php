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
            $table->ulid('id')->primary();
            $table->string('number')->index();
            $table->date('date');
            $table->ulid('ledger_id')->index();
            $table->ulid('payment_transaction_id')->nullable()->index();
            $table->ulid('bank_transaction_id')->nullable()->index();
            $table->string('cheque_no')->nullable();
            $table->text('description')->nullable();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
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


