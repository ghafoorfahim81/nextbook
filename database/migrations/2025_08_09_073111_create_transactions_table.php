<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\TransactionType;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('transactions', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->char('account_id', 26)->index();
            $table->char('ledger_id', 26)->nullable()->index();
            $table->decimal('amount', 19, 4);
            $table->char('currency_id', 26)->index();
            $table->decimal('rate', 10, 6);
            $table->date('date')->index();
            $table->string('reference_type')->nullable()->index();
            $table->char('reference_id', 26)->nullable()->index();
            $table->enum('type', TransactionType::values())->default(TransactionType::CREDIT->value);
            $table->text('remark')->nullable();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->timestamps();
            $table->index(['reference_type', 'reference_id']);
            $table->softDeletes();
        });

        Schema::table('transactions', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('account_id')->references('id')->on('accounts');
            $table->foreign('ledger_id')->references('id')->on('ledgers');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
