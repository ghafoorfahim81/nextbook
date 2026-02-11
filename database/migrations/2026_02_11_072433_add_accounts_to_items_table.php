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
        Schema::table('items', function (Blueprint $table) {
            $table->ulid('cost_account_id')->index();
            $table->ulid('income_account_id')->index();
            $table->ulid('asset_account_id')->index();
            
        });
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('cost_account_id')->references('id')->on('accounts');
            $table->foreign('income_account_id')->references('id')->on('accounts');
            $table->foreign('asset_account_id')->references('id')->on('accounts');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['cost_account_id']);
            $table->dropForeign(['income_account_id']);
            $table->dropForeign(['asset_account_id']);
        });
    }
};
