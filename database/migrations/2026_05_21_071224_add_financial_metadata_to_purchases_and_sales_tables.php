<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Store currency/rate directly so drafts (no transaction yet) still carry financial metadata.
        // Also store initial payment details for credit purchases with partial upfront payment.
        Schema::table('purchases', function (Blueprint $table) {
            $table->ulid('currency_id')->nullable()->after('bank_account_id');
            $table->decimal('rate', 10, 4)->nullable()->default(1)->after('currency_id');
            $table->decimal('initial_payment_amount', 19, 4)->nullable()->default(0)->after('rate');
            $table->ulid('initial_payment_account_id')->nullable()->after('initial_payment_amount');
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->ulid('currency_id')->nullable()->after('discount_type');
            $table->decimal('rate', 10, 4)->nullable()->default(1)->after('currency_id');
            $table->decimal('initial_receipt_amount', 19, 4)->nullable()->default(0)->after('rate');
            $table->ulid('initial_receipt_account_id')->nullable()->after('initial_receipt_amount');
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table) {
            $table->dropColumn(['currency_id', 'rate', 'initial_payment_amount', 'initial_payment_account_id']);
        });

        Schema::table('sales', function (Blueprint $table) {
            $table->dropColumn(['currency_id', 'rate', 'initial_receipt_amount', 'initial_receipt_account_id']);
        });
    }
};
