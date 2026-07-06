<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\SaleReturnReason;
use App\Enums\TransactionStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('sale_returns', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->integer('number')->index();
            $table->ulid('sale_id')->index();
            $table->ulid('customer_id')->index();
            $table->date('date');
            $table->enum('reason', SaleReturnReason::values())->nullable();
            $table->text('description')->nullable();
            $table->enum('status', TransactionStatus::values())->default(TransactionStatus::POSTED->value);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('sale_returns', function (Blueprint $table) {
            $table->foreign('sale_id')->references('id')->on('sales');
            $table->foreign('customer_id')->references('id')->on('ledgers');
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
        Schema::dropIfExists('sale_returns');
    }
};
