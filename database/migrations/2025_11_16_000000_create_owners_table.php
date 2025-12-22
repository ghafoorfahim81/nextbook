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
        Schema::create('owners', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->index();
            $table->string('father_name');
            $table->string('nic')->nullable()->index();
            $table->string('email')->nullable();
            $table->text('address')->nullable();
            $table->string('phone_number')->nullable();
            $table->decimal('ownership_percentage', 5, 2)->default(100);
            $table->boolean('is_active')->default(true);
            $table->char('capital_transaction_id', 26)->nullable()->index();
            $table->char('account_transaction_id', 26)->nullable()->index();
            $table->char('capital_account_id', 26)->nullable()->index();
            $table->char('drawing_account_id', 26)->nullable()->index();
            $table->char('branch_id', 26)->index();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['branch_id', 'name', 'deleted_at']);
            $table->unique(['branch_id', 'nic', 'deleted_at']);
            $table->unique(['branch_id', 'email', 'deleted_at']);
            $table->unique(['branch_id', 'phone_number', 'deleted_at']);
        });

        Schema::table('owners', function (Blueprint $table) {
            // Foreign keys
            $table->foreign('capital_transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('account_transaction_id')->references('id')->on('transactions')->onDelete('set null');

            $table->foreign('capital_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('drawing_account_id')->references('id')->on('accounts')->onDelete('set null');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('owners');
    }
};


