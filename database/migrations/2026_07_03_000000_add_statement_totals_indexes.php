<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->index(['ledger_id', 'deleted_at'], 'transaction_lines_ledger_deleted_idx');
            $table->index(['account_id', 'deleted_at'], 'transaction_lines_account_deleted_idx');
        });
    }

    public function down(): void
    {
        Schema::table('transaction_lines', function (Blueprint $table) {
            $table->dropIndex('transaction_lines_account_deleted_idx');
            $table->dropIndex('transaction_lines_ledger_deleted_idx');
        });
    }
};
