<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every one of these tables already carries size_id; colour is the missing
     * half of the variant pair, so transaction lines can record exactly which
     * colour/size was bought, sold, ordered, returned or adjusted.
     */
    private const TABLES = [
        'purchase_items',
        'sale_items',
        'sale_order_items',
        'sale_return_items',
        'stock_adjustment_items',
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->string('color')->nullable()->after('batch');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('color');
            });
        }
    }
};
