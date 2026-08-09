<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renames `credit_limit_status` (Block/Indicate) to `credit_terms`
     * (strict/warning/flexible) and adds the `credit_limit_enabled` flag.
     * PostgreSQL-specific: the original `enum()` column is a varchar + CHECK constraint.
     */
    public function up(): void
    {
        Schema::table('ledgers', function (Blueprint $table) {
            $table->boolean('credit_limit_enabled')->default(false)->after('credit_limit');
        });

        // Rename the column and re-map its values / constraint.
        DB::statement('ALTER TABLE ledgers RENAME COLUMN credit_limit_status TO credit_terms');
        DB::statement('ALTER TABLE ledgers DROP CONSTRAINT IF EXISTS ledgers_credit_limit_status_check');
        DB::statement('ALTER TABLE ledgers ALTER COLUMN credit_terms DROP DEFAULT');

        // Map legacy values onto the new term set.
        DB::statement("UPDATE ledgers SET credit_terms = 'strict' WHERE credit_terms = 'Block'");
        DB::statement("UPDATE ledgers SET credit_terms = 'warning' WHERE credit_terms = 'Indicate'");

        DB::statement("ALTER TABLE ledgers ALTER COLUMN credit_terms SET DEFAULT 'warning'");
        DB::statement("ALTER TABLE ledgers ADD CONSTRAINT ledgers_credit_terms_check CHECK (credit_terms IN ('strict', 'warning', 'flexible'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE ledgers DROP CONSTRAINT IF EXISTS ledgers_credit_terms_check');
        DB::statement('ALTER TABLE ledgers ALTER COLUMN credit_terms DROP DEFAULT');

        // Map new terms back onto the legacy set (flexible has no legacy equivalent → Indicate).
        DB::statement("UPDATE ledgers SET credit_terms = 'Block' WHERE credit_terms = 'strict'");
        DB::statement("UPDATE ledgers SET credit_terms = 'Indicate' WHERE credit_terms IN ('warning', 'flexible')");

        DB::statement("ALTER TABLE ledgers ALTER COLUMN credit_terms SET DEFAULT 'Indicate'");
        DB::statement("ALTER TABLE ledgers ADD CONSTRAINT ledgers_credit_limit_status_check CHECK (credit_terms IN ('Block', 'Indicate'))");
        DB::statement('ALTER TABLE ledgers RENAME COLUMN credit_terms TO credit_limit_status');

        Schema::table('ledgers', function (Blueprint $table) {
            $table->dropColumn('credit_limit_enabled');
        });
    }
};
