<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('account_transfers', function (Blueprint $table) {
            $table->ulid('from_account_id')->nullable()->index()->after('transaction_id');
            $table->ulid('to_account_id')->nullable()->index()->after('from_account_id');

            $table->foreign('from_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('to_account_id')->references('id')->on('accounts')->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('account_transfers', function (Blueprint $table) {
            $table->dropForeign(['from_account_id']);
            $table->dropForeign(['to_account_id']);
            $table->dropColumn(['from_account_id', 'to_account_id']);
        });

        Schema::enableForeignKeyConstraints();
    }
};
