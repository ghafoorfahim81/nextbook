<?php

use App\Enums\CreditLimitStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->ulid('group_id')->nullable()->index()->after('currency_id');
            $table->ulid('payment_term_id')->nullable()->index()->after('group_id');
            $table->ulid('country_id')->nullable()->index()->after('payment_term_id');
            $table->ulid('province_id')->nullable()->index()->after('country_id');
            $table->double('credit_limit')->nullable()->after('province_id');
            $table->enum('credit_limit_status', CreditLimitStatus::values())
                ->default(CreditLimitStatus::INDICATE->value)
                ->after('credit_limit');
            $table->double('discount')->nullable()->after('credit_limit_status');
            $table->string('whatsapp_number')->nullable()->after('phone_no');
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->foreign('group_id')->references('id')->on('customer_groups')->nullOnDelete();
            $table->foreign('payment_term_id')->references('id')->on('payment_terms')->nullOnDelete();
            $table->foreign('country_id')->references('id')->on('countries')->nullOnDelete();
            $table->foreign('province_id')->references('id')->on('provinces')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropForeign(['group_id', 'payment_term_id', 'country_id', 'province_id']);
            $table->dropColumn([
                'group_id',
                'payment_term_id',
                'country_id',
                'province_id',
                'credit_limit',
                'credit_limit_status',
                'discount',
                'whatsapp_number',
            ]);
        });
    }
};
