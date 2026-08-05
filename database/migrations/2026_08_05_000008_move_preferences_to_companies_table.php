<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Preferences belong to the company, not to each employee.
 *
 * They currently live on `users`, which means two staff at the same shop can
 * see different item forms, and a new hire gets the hardcoded defaults from
 * User::defaultPreferences() instead of the shape their business actually
 * uses. Field visibility and the "Batch"/"Lot"/"Expiry" label are properties
 * of the trade, not of the person logged in.
 *
 * users.preferences is left in place and becomes a per-user override layered
 * on top of the company's, so nothing breaks while the UI catches up.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->json('preferences')->nullable()->after('business_type');
        });

        // Seed each company from one of its existing users, preferring the
        // earliest (most likely the owner who configured the system).
        $companies = DB::table('companies')->pluck('id');

        foreach ($companies as $companyId) {
            $preferences = DB::table('users')
                ->where('company_id', $companyId)
                ->whereNotNull('preferences')
                ->orderBy('created_at')
                ->value('preferences');

            if ($preferences !== null) {
                DB::table('companies')
                    ->where('id', $companyId)
                    ->update(['preferences' => $preferences]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn('preferences');
        });
    }
};
