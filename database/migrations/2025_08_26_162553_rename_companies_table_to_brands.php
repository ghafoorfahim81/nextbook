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
        // Rename the companies table to brands
        Schema::rename('companies', 'brands');

        // Update foreign key references in items table
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['company_id']);
            $table->renameColumn('company_id', 'brand_id');
        });

        // Add the foreign key constraint back
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('brand_id')->references('id')->on('brands');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Remove foreign key constraint
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['brand_id']);
        });

        // Rename column back
        Schema::table('items', function (Blueprint $table) {
            $table->renameColumn('brand_id', 'company_id');
        });

        // Add foreign key constraint back
        Schema::table('items', function (Blueprint $table) {
            $table->foreign('company_id')->references('id')->on('companies');
        });

        // Rename the brands table back to companies
        Schema::rename('brands', 'companies');
    }
};
