<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table) {
            $table->index(['branch_id', 'status', 'date'], 'sales_branch_status_date_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->index(['branch_id', 'status', 'date'], 'purchases_branch_status_date_idx');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->index(['transaction_id', 'account_id'], 'transaction_lines_transaction_account_idx');
            $table->index(['transaction_id', 'ledger_id'], 'transaction_lines_transaction_ledger_idx');
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->index(['branch_id', 'item_id'], 'stock_balances_branch_item_idx');
            $table->index(['branch_id', 'expire_date'], 'stock_balances_branch_expire_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropIndex('stock_balances_branch_expire_idx');
            $table->dropIndex('stock_balances_branch_item_idx');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex('transaction_lines_transaction_ledger_idx');
            $table->dropIndex('transaction_lines_transaction_account_idx');
        });

        Schema::table('purchases', function (Blueprint $table) {
            $table->dropIndex('purchases_branch_status_date_idx');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropIndex('sales_branch_status_date_idx');
        });
    }
};
