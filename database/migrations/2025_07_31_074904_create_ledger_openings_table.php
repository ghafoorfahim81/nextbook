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
        Schema::disableForeignKeyConstraints();

        Schema::create('ledger_openings', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->morphs('transactionable');
            $table->morphs('ledgerable');
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
        });

        Schema::table('ledger_openings', function (Blueprint $table) {
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });

        Schema::enableForeignKeyConstraints();
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('ledger_openings');
    }
};
