<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Phase 6: modules can be switched off per company, and a business type
 * starts some of them off (a grocery without HR, config/business_profiles.php).
 *
 * That default is for companies created from now on. A company that exists
 * already has been using every module, so each one it has gets written down
 * as on here, and nothing disappears from anyone's menu. A company that has
 * chosen already (it has a `modules` entry) is left as it is.
 */
return new class extends Migration
{
    private const MODULES = ['hr', 'owners', 'expenses', 'landed_costs', 'discount_rules'];

    public function up(): void
    {
        foreach (DB::table('companies')->get(['id', 'preferences']) as $company) {
            $preferences = (array) json_decode((string) $company->preferences, true);

            if (array_key_exists('modules', $preferences)) {
                continue;
            }

            $preferences['modules'] = array_fill_keys(self::MODULES, true);

            DB::table('companies')->where('id', $company->id)->update([
                'preferences' => json_encode($preferences),
            ]);
        }
    }

    public function down(): void
    {
        // Nothing to undo: without the code that reads it, the entry is unused.
    }
};
