<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Step 1 of line-level multi-currency.
 *
 * Adds the columns that let a single voucher carry lines at different rates.
 * Everything stays nullable here so this can be deployed ahead of the backfill;
 * a later migration flips them to NOT NULL once verification reports clean.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->ulid('base_currency_id')->nullable()->index()->after('currency_id');
        });

        // Rates like IRR -> AFN sit around 0.0000017 and round to zero at four
        // decimals, silently valuing a whole voucher at nothing. Widening in
        // place is lossless; Postgres rewrites the table under ACCESS EXCLUSIVE,
        // so run this in a maintenance window.
        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('rate', 19, 8)->change();
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->ulid('currency_id')->nullable()->after('ledger_id');
            $table->decimal('rate', 19, 8)->nullable()->after('currency_id');
            $table->decimal('base_debit', 19, 4)->nullable()->after('credit');
            $table->decimal('base_credit', 19, 4)->nullable()->after('base_debit');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('base_currency_id')->references('id')->on('currencies');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['currency_id', 'rate', 'base_debit', 'base_credit']);
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['base_currency_id']);
            $table->dropColumn('base_currency_id');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->decimal('rate', 10, 4)->change();
        });
    }
};
