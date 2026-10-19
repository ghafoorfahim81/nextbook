<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Model / style number.
 *
 * Item-level rather than variant-level: a "Latitude 5540" is the model, and
 * its 8GB and 16GB variants share it. Same for a garment style number and an
 * auto part's OEM reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('items', 'model')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->string('model')->nullable()->after('manufacturer')->index();
        });
    }

    public function down(): void
    {
        if (! Schema::hasColumn('items', 'model')) {
            return;
        }

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('model');
        });
    }
};
