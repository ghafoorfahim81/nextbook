<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A transfer may or may not carry a freight cost, and the form now says so
 * explicitly instead of inferring it from a non-zero amount — an operator who
 * types a cost and then clears it should not leave a stray posting behind.
 *
 * When it does carry one, the cost is paid from a real bank/cash account in a
 * real currency, so it needs the same currency + rate pair every other
 * money-moving document carries: `transfer_cost` is the amount in
 * `currency_id`, and `rate` converts it to home currency for the GL.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_transfers', function (Blueprint $table) {
            $table->boolean('has_transfer_cost')->default(false)->after('status');
            $table->ulid('bank_account_id')->nullable()->after('transfer_cost')->index();
            $table->ulid('expense_account_id')->nullable()->after('bank_account_id')->index();
            $table->ulid('currency_id')->nullable()->after('expense_account_id')->index();
            $table->decimal('rate', 19, 6)->nullable()->after('currency_id');

            $table->foreign('bank_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('expense_account_id')->references('id')->on('accounts')->nullOnDelete();
            $table->foreign('currency_id')->references('id')->on('currencies')->nullOnDelete();
        });

        // Existing transfers that already recorded a cost kept the switch off by
        // default; flip it on so their Show page still reads back correctly.
        DB::table('item_transfers')->where('transfer_cost', '>', 0)->update([
            'has_transfer_cost' => true,
        ]);
    }

    public function down(): void
    {
        Schema::table('item_transfers', function (Blueprint $table) {
            $table->dropForeign(['bank_account_id']);
            $table->dropForeign(['expense_account_id']);
            $table->dropForeign(['currency_id']);
            $table->dropColumn(['has_transfer_cost', 'bank_account_id', 'expense_account_id', 'currency_id', 'rate']);
        });
    }
};
