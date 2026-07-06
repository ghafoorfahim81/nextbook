<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use App\Enums\DiscountType;
use App\Enums\SaleOrderStatus;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('sale_orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->integer('number')->index();
            $table->date('date');
            $table->date('delivery_date')->nullable();
            $table->ulid('customer_id')->index();
            $table->ulid('currency_id')->nullable()->index();
            $table->decimal('rate', 10, 4)->nullable();
            $table->ulid('warehouse_id')->nullable()->index();
            $table->decimal('discount', 10, 2)->nullable();
            $table->enum('discount_type', DiscountType::values())->nullable();
            $table->enum('status', SaleOrderStatus::values())->default(SaleOrderStatus::DRAFT->value);
            $table->text('note')->nullable();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->softDeletes();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('sale_orders', function (Blueprint $table) {
            $table->foreign('customer_id')->references('id')->on('ledgers');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('warehouse_id')->references('id')->on('warehouses');
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
        Schema::dropIfExists('sale_orders');
    }
};
