<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('account_balances', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('financial_period_id')->index();
            $table->ulid('account_id')->index();
            $table->decimal('base_debit', 19, 4)->default(0);
            $table->decimal('base_credit', 19, 4)->default(0);
            $table->decimal('base_balance', 19, 4)->default(0);
            $table->enum('balance_type', ['dr', 'cr'])->default('dr');
            $table->timestamp('snapshot_at');
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->timestamps();
        });

        Schema::table('account_balances', function (Blueprint $table) {
            $table->foreign('financial_period_id')->references('id')->on('financial_periods');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('account_balances');
    }
};
