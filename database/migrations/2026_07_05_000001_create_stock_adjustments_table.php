<?php

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Enums\TransactionStatus;
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

        Schema::create('stock_adjustments', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('reference')->index();
            $table->date('date')->index();
            $table->enum('type', StockMovementType::values());
            $table->enum('reason', StockAdjustmentReason::values());
            $table->ulid('warehouse_id')->index();
            $table->enum('status', TransactionStatus::values())->default(TransactionStatus::DRAFT->value);
            $table->ulid('branch_id')->index();
            $table->text('notes')->nullable();
            $table->ulid('fiscal_period_id')->nullable()->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::table('stock_adjustments', function (Blueprint $table) {
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('fiscal_period_id')->references('id')->on('financial_periods');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('stock_adjustments');
    }
};
