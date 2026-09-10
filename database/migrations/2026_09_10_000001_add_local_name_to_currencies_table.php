<?php

use App\Models\Administration\Currency;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->string('local_name')->nullable()->after('name');
        });

        // Backfill the localized name for the currencies the app ships with.
        foreach (Currency::defaultCurrencies() as $code => $definition) {
            if (empty($definition['local_name'])) {
                continue;
            }

            DB::table('currencies')
                ->where('code', $code)
                ->whereNull('local_name')
                ->update(['local_name' => $definition['local_name']]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('currencies', function (Blueprint $table) {
            $table->dropColumn('local_name');
        });
    }
};
