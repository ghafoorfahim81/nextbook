<?php

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('landed_costs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->date('date');
            $table->ulid('purchase_id')->nullable()->index();
            $table->decimal('total_cost', 12, 2)->default(0);
            $table->decimal('allocated_total', 12, 2)->default(0);
            $table->enum('allocation_method', LandedCostAllocationMethod::values())->default(LandedCostAllocationMethod::ByValue->value);
            $table->enum('status', LandedCostStatus::values())->default(LandedCostStatus::Draft->value);
            $table->text('notes')->nullable();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by_id')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'purchase_id']);
            $table->index(['branch_id', 'date']);
            $table->index(['branch_id', 'status']);
            $table->index(['branch_id', 'allocation_method']);
        });

        Schema::table('landed_costs', function (Blueprint $table): void {
            $table->foreign('purchase_id')->references('id')->on('purchases')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by_id')->references('id')->on('users')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_costs');
    }
};
