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

        Schema::create('currencies', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->unique();
            $table->string('code')->unique();
            $table->string('symbol');
            $table->string('format');
            $table->decimal('exchange_rate');
            $table->boolean('is_active');
            $table->string('flag')->unique();
            $table->char('branch_id', 26);
            $table->char('tenant_id', 26)->nullable();
            $table->char('created_by', 26);
            $table->char('updated_by', 26)->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
        
        Schema::table('currencies', function (Blueprint $table) {
            $table->foreign('branch_id')
                ->references('id')
                ->on('branches')
                ->onDelete('CASCADE');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
