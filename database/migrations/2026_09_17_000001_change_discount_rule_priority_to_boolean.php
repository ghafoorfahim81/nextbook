<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Priority becomes a yes/no switch.
 *
 * It was an integer, which invited "4", "5", "100" — numbers that mean nothing
 * to anyone reading the rule later and only ever get compared to each other.
 * All it ever decided was which of two equally specific rules wins, so a plain
 * switch says the same thing without the guesswork.
 *
 * Any non-zero priority already in the table means "this one first", so it maps
 * straight onto true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->boolean('is_priority')->default(false)->after('ends_at');
        });

        DB::table('discount_rules')->where('priority', '>', 0)->update(['is_priority' => true]);

        Schema::table('discount_rules', function (Blueprint $table) {
            $table->dropColumn('priority');
        });
    }

    public function down(): void
    {
        Schema::table('discount_rules', function (Blueprint $table) {
            $table->integer('priority')->default(0)->after('ends_at');
        });

        DB::table('discount_rules')->where('is_priority', true)->update(['priority' => 1]);

        Schema::table('discount_rules', function (Blueprint $table) {
            $table->dropColumn('is_priority');
        });
    }
};
