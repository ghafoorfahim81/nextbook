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

        Schema::create('ledger_openings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            // Polymorphic relation to ledger: Customer, Supplier, Account, etc.
            $table->char('ledgerable_id', 26)->index();
            $table->string('ledgerable_type');
            $table->index(['ledgerable_id', 'ledgerable_type']);

            // Transaction morph relation
            $table->char('transaction_id', 26)->index();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('ledger_openings', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');

        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_openings');
    }
};
