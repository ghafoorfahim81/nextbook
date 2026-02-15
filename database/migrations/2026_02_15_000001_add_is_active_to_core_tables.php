<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('unit_measures', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('sizes', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->index();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('unit_measures', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('sizes', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('stores', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};

