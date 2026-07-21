<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Color and size become part of the stock balance identity, alongside
     * batch and expire_date, so stock can be held per colour/size variant.
     * Both are nullable: items that track neither keep a single row.
     */
    public function up(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->string('color')->nullable()->after('batch');
            $table->ulid('size_id')->nullable()->after('color');

            $table->foreign('size_id')->references('id')->on('sizes');
            $table->index(
                ['branch_id', 'item_id', 'warehouse_id', 'color', 'size_id'],
                'stock_balances_variant_index'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropIndex('stock_balances_variant_index');
            $table->dropForeign(['size_id']);
            $table->dropColumn(['color', 'size_id']);
        });
    }
};
