<?php

use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['expenses', 'receipts', 'payments', 'account_transfers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (!Schema::hasColumn($tableName, 'status')) {
                    $table->string('status')->default(TransactionStatus::POSTED->value)->index();
                }
            });
        }
    }

    public function down(): void
    {
        foreach (['expenses', 'receipts', 'payments', 'account_transfers'] as $tableName) {
            Schema::table($tableName, function (Blueprint $table) use ($tableName) {
                if (Schema::hasColumn($tableName, 'status')) {
                    $table->dropColumn('status');
                }
            });
        }
    }
};
