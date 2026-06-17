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
            $table->dropForeign(['transaction_id']);
            $table->dropColumn('transaction_id');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::table('account_transfers', function (Blueprint $table) {
            $table->ulid('transaction_id')->nullable()->index();
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }
};
