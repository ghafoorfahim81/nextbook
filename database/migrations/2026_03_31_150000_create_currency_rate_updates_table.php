<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('currency_rate_updates', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('currency_id')->index();
            $table->decimal('exchange_rate', 24, 8);
            $table->date('date')->index();
            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->timestamps();

            $table->index(['branch_id', 'currency_id', 'date']);
        });

        Schema::enableForeignKeyConstraints();

        Schema::table('currency_rate_updates', function (Blueprint $table) {
            $table->foreign('currency_id')->references('id')->on('currencies')->onDelete('cascade');
            $table->foreign('branch_id')->references('id')->on('branches')->onDelete('cascade');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('currency_rate_updates');
    }
};
