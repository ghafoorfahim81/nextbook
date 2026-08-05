<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * `is_active` is declared in Category::$fillable and cast to boolean, but no
 * migration ever created the column — so assigning it silently did nothing.
 *
 * Guarded, because some databases already picked the column up out of band
 * during this refactor and would otherwise fail on a duplicate column.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('categories', 'is_active')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->boolean('is_active')->default(true)->after('remark');
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('categories', 'is_active')) {
            return;
        }

        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
