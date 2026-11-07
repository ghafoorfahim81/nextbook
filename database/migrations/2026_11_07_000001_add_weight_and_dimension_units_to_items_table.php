<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Weight and dimensions were stored as bare numbers. Each item now says which
 * unit they are in: one weight unit, and one length unit shared by length,
 * width and height (a box is never 30 cm x 2 m x 5 in).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->ulid('weight_unit_id')->nullable()->after('weight');
            $table->ulid('dimension_unit_id')->nullable()->after('height');

            $table->foreign('weight_unit_id')->references('id')->on('unit_measures')->nullOnDelete();
            $table->foreign('dimension_unit_id')->references('id')->on('unit_measures')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['weight_unit_id']);
            $table->dropForeign(['dimension_unit_id']);
            $table->dropColumn(['weight_unit_id', 'dimension_unit_id']);
        });
    }
};
