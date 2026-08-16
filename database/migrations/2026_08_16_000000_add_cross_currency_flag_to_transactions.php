<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Records that a voucher was deliberately posted as cross-currency.
 *
 * TransactionService normally requires every non-base currency on an entry to
 * self-balance. A USD invoice settled with AFN cash cannot: the dollars are
 * relieved by afghanis and no dollar counterpart exists. That is legitimate,
 * but it has to be declared rather than inferred — the agreed conversion is a
 * commercial decision, not arithmetic.
 *
 * Storing the declaration means an auditor looking at a one-sided USD line a
 * year from now can tell it was intended, instead of guessing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->boolean('is_cross_currency')->default(false)->after('rate');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropColumn('is_cross_currency');
        });
    }
};
