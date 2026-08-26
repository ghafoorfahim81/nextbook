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

        Schema::create('landed_cost_category_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('landed_cost_id')->index();
            $table->ulid('landed_cost_category_id')->index();
            $table->decimal('amount', 12, 2)->default(0);
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['landed_cost_id', 'landed_cost_category_id']);
            $table->index(['branch_id', 'landed_cost_id']);
        });

        Schema::table('landed_cost_category_allocations', function (Blueprint $table): void {
            $table->foreign('landed_cost_id')->references('id')->on('landed_costs')->cascadeOnDelete();
            $table->foreign('landed_cost_category_id')->references('id')->on('landed_cost_categories');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            $table->foreign('deleted_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('landed_cost_category_allocations');
    }
};
