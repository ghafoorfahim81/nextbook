<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('account_transfers', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('number')->nullable()->index();
            $table->date('date');
            $table->char('from_transaction_id', 26)->nullable();
            $table->char('to_transaction_id', 26)->nullable();
            $table->text('remark')->nullable();
            $table->char('branch_id', 26)->nullable()->index();
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by', 26)->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('account_transfers', function (Blueprint $table) {
            $table->foreign('from_transaction_id')->references('id')->on('transactions');
            $table->foreign('to_transaction_id')->references('id')->on('transactions');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('account_transfers');
    }
};


