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
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->dropColumn(['average_cost']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('stock_balances', function (Blueprint $table) {
            $table->decimal('average_cost', 18, 4)->nullable()->after('quantity');
        });
    }
};

