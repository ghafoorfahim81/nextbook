<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Retires the two tables settlements replaces.
 *
 * `sale_receives` held sale_id + receipt_id + amount — a real allocation table,
 * whose rows the previous migration carried into settlements. It is dropped
 * rather than demoted to a document-level link because it has no document-level
 * columns to keep (no method, no attachment, no reference): everything on it is
 * allocation, and allocation now lives in exactly one place. Leaving amount in
 * both would give two answers to "how much is left on this invoice", and within
 * a year they would disagree.
 *
 * `purchase_payments` was never finished. Its amount column was bolted on late
 * and it stored no rate, so there is nothing to preserve — the purchase payment
 * module is rebuilt against settlements instead of backfilled.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('sale_receives');
        Schema::dropIfExists('purchase_payments');
    }

    public function down(): void
    {
        Schema::create('sale_receives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('sale_id');
            $table->ulid('receipt_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->ulid('created_by');
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('branch_id');
            $table->index(['branch_id', 'sale_id']);
            $table->index(['branch_id', 'receipt_id']);
            $table->index(['branch_id', 'created_by']);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('purchase_payments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('purchase_id');
            $table->ulid('payment_id');
            $table->decimal('amount', 15, 2)->default(0);
            $table->ulid('created_by');
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->ulid('branch_id');
            $table->index(['branch_id', 'purchase_id']);
            $table->index(['branch_id', 'payment_id']);
            $table->index(['branch_id', 'created_by']);
            $table->timestamps();
            $table->softDeletes();
        });

        // The rows are not restored. They were migrated into settlements, and
        // recreating them from there would recreate the second source of truth
        // this change exists to remove.
    }
};
