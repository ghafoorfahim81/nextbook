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

        Schema::create('stores', function (Blueprint $table) {
            $table->char('id', 26)->primary();
            $table->string('name')->unique();
            $table->string('address')->nullable();
            $table->boolean('is_main');
            $table->char('branch_id',26);
            $table->char('created_by',26);
            $table->char('updated_by',26)->nullable();
            $table->timestamps();
        });

        Schema::enableForeignKeyConstraints();
        Schema::table('stores', function (Blueprint $table) {
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
        Schema::dropIfExists('stores');
    }
};
