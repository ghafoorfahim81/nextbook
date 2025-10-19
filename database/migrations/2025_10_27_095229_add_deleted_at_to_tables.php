<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $tables = [
            'categories',
            'departments',
            'designations',
            'items',
            'brands',
            'currencies',
            'accounts',
            'transactions',
            'account_types',
            'unit_measures',
            'quantities',
            'stock_openings',
            'ledgers',
            'ledger_openings',
            'users',
            'branches',
        ];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->softDeletes();
                $table->char('deleted_by', 26)->nullable();
                $table->foreign('deleted_by')->references('id')->on('users')->onDelete('set null');
            });
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        $tables = [
            'categories',
            'departments',
            'designations',
            'items',
            'brands',
            'currencies',
            'accounts',
            'transactions',
            'account_types',
            'unit_measures',
            'quantities',
            'stock_openings',
            'ledgers',
            'ledger_openings',
            'users',
            'branches',
        ];
        foreach ($tables as $table) {
            Schema::table($table, function (Blueprint $table) {
                $table->dropForeign(['deleted_by']);
                $table->dropColumn('deleted_by');
                $table->dropSoftDeletes();
            });
        }
    }
};
