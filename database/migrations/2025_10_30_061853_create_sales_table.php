<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\SalesPurchaseType;
use App\Enums\TransactionStatus;
use App\Enums\DiscountType;
return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('sales', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->integer('number')->index();
            $table->char('customer_id', 26)->index();
            $table->date('date');
            $table->char('transaction_id', 26)->nullable()->index();
            $table->decimal('discount', 10, 2)->nullable();
            $table->enum('discount_type', DiscountType::values())->nullable();
            $table->enum('type', SalesPurchaseType::values())->default(SalesPurchaseType::Cash->value);
            $table->text('description')->nullable();
            $table->enum('status', TransactionStatus::values())->default(TransactionStatus::PENDING->value);
            $table->char('store_id', 26)->index();
            $table->char('branch_id', 26)->index();
            $table->char('created_by', 26)->index();
            $table->char('updated_by', 26)->nullable();
            $table->char('deleted_by',26)->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('sales', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('ledgers');
            $table->foreign('transaction_id')->references('id')->on('transactions')->onDelete('set null');
            $table->foreign('store_id')->references('id')->on('stores')->onDelete('set null');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('sales');
    }
};
