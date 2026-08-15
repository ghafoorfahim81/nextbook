<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Step 4 of line-level multi-currency: enforce.
 *
 * Deliberately a separate file from the add/backfill migrations so it can be
 * held back and run only once `php artisan transactions:verify-base` reports
 * clean. Running this against unverified data will fail on the check
 * constraint rather than corrupt anything.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->ulid('currency_id')->nullable(false)->change();
            $table->decimal('rate', 19, 8)->nullable(false)->change();
            $table->decimal('base_debit', 19, 4)->default(0)->nullable(false)->change();
            $table->decimal('base_credit', 19, 4)->default(0)->nullable(false)->change();
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            // Per-currency account balances — a cash box holds more than one.
            $table->index(['account_id', 'currency_id'], 'transaction_lines_account_currency_idx');
            // Customer/supplier subledger, split by the currency of the debt.
            $table->index(['ledger_id', 'currency_id'], 'transaction_lines_ledger_currency_idx');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE transaction_lines
            ADD CONSTRAINT chk_single_side
            CHECK (
                (debit = 0 OR credit = 0)
                AND (base_debit = 0 OR base_credit = 0)
            )
        SQL);
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE transaction_lines DROP CONSTRAINT IF EXISTS chk_single_side');

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex('transaction_lines_account_currency_idx');
            $table->dropIndex('transaction_lines_ledger_currency_idx');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->ulid('currency_id')->nullable()->change();
            $table->decimal('rate', 19, 8)->nullable()->change();
            $table->decimal('base_debit', 19, 4)->nullable()->default(null)->change();
            $table->decimal('base_credit', 19, 4)->nullable()->default(null)->change();
        });
    }
};
