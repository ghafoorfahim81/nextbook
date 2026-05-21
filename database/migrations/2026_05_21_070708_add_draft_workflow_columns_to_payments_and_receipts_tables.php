<?php

use App\Enums\TransactionStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->enum('status', TransactionStatus::values())->default(TransactionStatus::DRAFT->value)->after('narration');
            $table->ulid('posted_by')->nullable()->after('status');
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->ulid('reversal_of_id')->nullable()->after('posted_at');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->enum('status', TransactionStatus::values())->default(TransactionStatus::DRAFT->value)->after('narration');
            $table->ulid('posted_by')->nullable()->after('status');
            $table->timestamp('posted_at')->nullable()->after('posted_by');
            $table->ulid('reversal_of_id')->nullable()->after('posted_at');
            $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn(['status', 'posted_by', 'posted_at', 'reversal_of_id', 'reversed_at']);
        });

        Schema::table('receipts', function (Blueprint $table) {
            $table->dropColumn(['status', 'posted_by', 'posted_at', 'reversal_of_id', 'reversed_at']);
        });
    }
};
