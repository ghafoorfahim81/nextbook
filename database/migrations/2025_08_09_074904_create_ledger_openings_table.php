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
            $table->ulid('id')->primary();
            // Polymorphic relation to ledger: Customer, Supplier, Account, etc.
            $table->ulid('ledgerable_id')->index();
            $table->string('ledgerable_type');
            $table->index(['ledgerable_id', 'ledgerable_type']);

            // Transaction morph relation
            $table->ulid('transaction_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
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
