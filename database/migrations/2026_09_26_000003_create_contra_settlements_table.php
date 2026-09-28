<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The set-off document: "we agreed to cancel what Ahmad owes us against what
 * we owe Ahmad, and only the difference moves."
 *
 * A party who both buys from us and sells to us has two ledgers, because a
 * ledger's control account follows its type and one balance cannot be both a
 * receivable and a payable. This document is the bridge between them.
 *
 * The two settlement vouchers it posts are recorded by id rather than found by
 * reference_type, because both carry the same reference and only their ids
 * tell them apart afterwards.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contra_settlements', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('number')->index();
            $table->date('date');
            $table->ulid('customer_ledger_id')->index();
            $table->ulid('supplier_ledger_id')->index();
            $table->ulid('currency_id')->index();
            $table->decimal('rate', 20, 8)->default(1);
            $table->decimal('amount', 20, 4);
            $table->text('narration')->nullable();
            $table->string('status')->default('posted')->index();
            // The two halves. Nullable because a reversal keeps the document
            // and its trail while the vouchers behind it are reversed.
            $table->ulid('customer_transaction_id')->nullable()->index();
            $table->ulid('supplier_transaction_id')->nullable()->index();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->ulid('deleted_by')->nullable();
        });

        Schema::table('contra_settlements', function (Blueprint $table) {
            $table->foreign('customer_ledger_id')->references('id')->on('ledgers');
            $table->foreign('supplier_ledger_id')->references('id')->on('ledgers');
            $table->foreign('currency_id')->references('id')->on('currencies');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contra_settlements');
    }
};
