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

        Schema::create('purchases', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('number')->index();
            $table->char('supplier_id', 26);
            $table->date('date');
            $table->char('transaction_id', 26);
            $table->decimal('discount', 10, 2)->nullable();
            $table->string('discount_type')->nullable();
            $table->string('type')->default('cash');
            $table->text('description')->nullable();
            $table->string('status')->default('pending');
            $table->char('branch_id', 26);
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('purchases', function (Blueprint $table) {
            $table->foreign('supplier_id')->references('id')->on('ledgers');
            $table->foreign('transaction_id')->references('id')->on('transactions');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('purchases');
    }
};
