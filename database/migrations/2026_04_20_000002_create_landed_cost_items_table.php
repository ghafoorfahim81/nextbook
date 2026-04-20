<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('landed_cost_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('landed_cost_id')->index();
            $table->ulid('purchase_item_id')->nullable()->index();
            $table->ulid('item_id')->index();
            $table->decimal('quantity', 12, 4)->default(0);
            $table->decimal('unit_cost', 12, 4)->default(0);
            $table->decimal('weight', 12, 4)->default(0);
            $table->decimal('volume', 12, 4)->default(0);
            $table->ulid('warehouse_id')->nullable()->index();
            $table->string('batch')->nullable()->index();
            $table->date('expire_date')->nullable()->index();
            $table->decimal('allocated_percentage', 10, 4)->default(0);
            $table->decimal('allocated_amount', 12, 2)->default(0);
            $table->decimal('item_cost_before', 12, 2)->default(0);
            $table->decimal('item_cost_after', 12, 2)->default(0);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'landed_cost_id']);
            $table->index(['branch_id', 'item_id']);
            $table->index(['branch_id', 'purchase_item_id']);
        });

        Schema::table('landed_cost_items', function (Blueprint $table): void {
            $table->foreign('landed_cost_id')->references('id')->on('landed_costs')->cascadeOnDelete();
            $table->foreign('purchase_item_id')->references('id')->on('purchase_items')->nullOnDelete();
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('warehouse_id')->references('id')->on('warehouses')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_items');
    }
};
