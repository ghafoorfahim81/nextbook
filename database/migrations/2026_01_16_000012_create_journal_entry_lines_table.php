<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('journal_entry_lines', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('journal_entry_id')->index();
            $table->ulid('account_id')->index();
            $table->text('description')->nullable();
            $table->decimal('debit', 19, 4)->default(0);
            $table->decimal('credit', 19, 4)->default(0);
            $table->ulid('currency_id')->index();
            $table->decimal('exchange_rate', 19, 6)->default(1);
            $table->decimal('base_debit', 19, 4)->default(0);
            $table->decimal('base_credit', 19, 4)->default(0);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('journal_entry_lines', function (Blueprint $table) {
            $table->foreign('journal_entry_id')->references('id')->on('journal_entries');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('journal_entry_lines');
    }
};
