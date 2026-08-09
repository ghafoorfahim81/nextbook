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
        Schema::create('provinces', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('country_id')->index();
            $table->string('name_en');
            $table->string('name_fa');
            $table->timestamps();

            $table->unique(['country_id', 'name_en']);
        });

        Schema::table('provinces', function (Blueprint $table) {
            $table->foreign('country_id')->references('id')->on('countries')->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provinces');
    }
};
