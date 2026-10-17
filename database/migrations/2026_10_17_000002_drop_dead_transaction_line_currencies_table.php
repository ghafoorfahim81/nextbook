<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Drops `transaction_line_currencies`.
 *
 * The table was created with nothing but `id` and `timestamps` — no foreign
 * key, no amount, no rate — and nothing ever wrote to it. The only reference
 * anywhere in the project was an empty `TransactionLineCurrency` model, removed
 * alongside this migration. Per-line currency is already carried on
 * `transaction_lines` itself.
 *
 * Left in place it would collect a `company_id` in the phase 2 migration and
 * become a permanently empty table that looks load-bearing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('transaction_line_currencies');
    }

    public function down(): void
    {
        Schema::create('transaction_line_currencies', function (Blueprint $table) {
            $table->id();
            $table->timestamps();
        });
    }
};
