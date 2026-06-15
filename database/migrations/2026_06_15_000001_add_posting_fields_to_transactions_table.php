<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (!Schema::hasColumn('transactions', 'posted_at')) {
                $table->timestamp('posted_at')->nullable()->after('status');
            }

            if (!Schema::hasColumn('transactions', 'posted_by')) {
                $table->ulid('posted_by')->nullable()->after('posted_at')->index();
                $table->foreign('posted_by')->references('id')->on('users')->nullOnDelete();
            }

            if (!Schema::hasColumn('transactions', 'reversal_of_id')) {
                $table->ulid('reversal_of_id')->nullable()->after('posted_by')->index();
                $table->foreign('reversal_of_id')->references('id')->on('transactions')->nullOnDelete();
            }

            if (!Schema::hasColumn('transactions', 'reversed_at')) {
                $table->timestamp('reversed_at')->nullable()->after('reversal_of_id');
            }

            if (!Schema::hasColumn('transactions', 'reversal_reason')) {
                $table->string('reversal_reason')->nullable()->after('reversed_at');
            }

            if (!Schema::hasColumn('transactions', 'posting_payload')) {
                $table->jsonb('posting_payload')->nullable()->after('reversal_reason');
            }
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            if (Schema::hasColumn('transactions', 'posted_by')) {
                $table->dropForeign(['posted_by']);
            }

            if (Schema::hasColumn('transactions', 'reversal_of_id')) {
                $table->dropForeign(['reversal_of_id']);
            }

            $table->dropColumn(array_values(array_filter([
                Schema::hasColumn('transactions', 'posted_at') ? 'posted_at' : null,
                Schema::hasColumn('transactions', 'posted_by') ? 'posted_by' : null,
                Schema::hasColumn('transactions', 'reversal_of_id') ? 'reversal_of_id' : null,
                Schema::hasColumn('transactions', 'reversed_at') ? 'reversed_at' : null,
                Schema::hasColumn('transactions', 'reversal_reason') ? 'reversal_reason' : null,
                Schema::hasColumn('transactions', 'posting_payload') ? 'posting_payload' : null,
            ])));
        });
    }
};
