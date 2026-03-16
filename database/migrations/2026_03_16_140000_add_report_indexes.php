<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->index(['branch_id', 'status', 'reference_type', 'date'], 'transactions_branch_status_reference_date_idx');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->index(['transaction_id', 'ledger_id', 'deleted_at'], 'transaction_lines_txn_ledger_deleted_idx');
            $table->index(['transaction_id', 'account_id', 'deleted_at'], 'transaction_lines_txn_account_deleted_idx');
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->index(['branch_id', 'type', 'deleted_at'], 'ledgers_branch_type_deleted_idx');
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->index(['branch_id', 'item_id', 'warehouse_id', 'status', 'deleted_at'], 'stock_balances_branch_item_warehouse_status_deleted_idx');
        });

        Schema::table('stock_movements', function (Blueprint $table) {
            $table->index(['branch_id', 'date', 'item_id', 'deleted_at'], 'stock_movements_branch_date_item_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('stock_movements', function (Blueprint $table) {
            $table->dropIndex('stock_movements_branch_date_item_deleted_idx');
        });

        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropIndex('stock_balances_branch_item_warehouse_status_deleted_idx');
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropIndex('ledgers_branch_type_deleted_idx');
        });

        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex('transaction_lines_txn_ledger_deleted_idx');
            $table->dropIndex('transaction_lines_txn_account_deleted_idx');
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->dropIndex('transactions_branch_status_reference_date_idx');
        });
    }
};
