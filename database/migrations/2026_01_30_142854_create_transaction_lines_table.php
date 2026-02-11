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
        Schema::create('transaction_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('transaction_id');
            $table->ulid('account_id');
            $table->ulid('ledger_id')->nullable();
            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);
            $table->string('bill_number')->nullable();
            $table->text('remark')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->index('transaction_id');
            $table->index(['account_id']);
            $table->index(['ledger_id']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_lines');
    }
};
