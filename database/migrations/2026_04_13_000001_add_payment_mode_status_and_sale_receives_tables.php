<?php

use App\Enums\PaymentMode;
use App\Enums\PaymentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sales', function (Blueprint $table): void {
            $table->enum('payment_status', PaymentStatus::values())
                ->default(PaymentStatus::Unpaid->value)
                ->after('status');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->enum('payment_status', PaymentStatus::values())
                ->default(PaymentStatus::Unpaid->value)
                ->after('status');
        });

        Schema::table('receipts', function (Blueprint $table): void {
            $table->enum('payment_mode', PaymentMode::values())
                ->default(PaymentMode::OnAccount->value)
                ->after('ledger_id');
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->enum('payment_mode', PaymentMode::values())
                ->default(PaymentMode::OnAccount->value)
                ->after('ledger_id');
        });

        Schema::table('purchase_payments', function (Blueprint $table): void {
            if (!Schema::hasColumn('purchase_payments', 'amount')) {
                $table->decimal('amount', 15, 2)->default(0)->after('payment_id');
            }
        });

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

        Schema::table('sale_receives', function (Blueprint $table): void {
            $table->foreign('sale_id')->references('id')->on('sales')->onDelete('cascade');
            $table->foreign('receipt_id')->references('id')->on('receipts')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sale_receives');

        Schema::table('purchase_payments', function (Blueprint $table): void {
            if (Schema::hasColumn('purchase_payments', 'amount')) {
                $table->dropColumn('amount');
            }
        });

        Schema::table('payments', function (Blueprint $table): void {
            $table->dropColumn('payment_mode');
        });

        Schema::table('receipts', function (Blueprint $table): void {
            $table->dropColumn('payment_mode');
        });

        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropColumn('payment_status');
        });

        Schema::table('sales', function (Blueprint $table): void {
            $table->dropColumn('payment_status');
        });
    }
};
