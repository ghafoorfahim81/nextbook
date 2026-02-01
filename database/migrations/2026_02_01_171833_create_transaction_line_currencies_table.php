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
        Schema::create('transaction_line_currencies', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->ulid('transaction_line_id');  // Link to transaction line
            $table->ulid('currency_id');          // Foreign currency
            $table->decimal('exchange_rate', 10, 6);     // Foreign amount
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->index(['transaction_line_id', 'currency_id']);
        });
        Schema::table('transaction_line_currencies', function (Blueprint $table) {
            $table->foreign('transaction_line_id')->references('id')->on('transaction_lines');
            $table->foreign('currency_id')->references('id')->on('currencies');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transaction_line_currencies');
    }
};
