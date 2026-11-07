<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Landed costs had no number of their own (the screens fell back to the ULID)
 * and could not be undone once posted. This adds the document number, numbered
 * oldest first within each company, and the `reversed` status.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('landed_costs', function (Blueprint $table) {
            $table->string('number')->nullable()->index()->after('company_id');
        });

        DB::statement("
            UPDATE landed_costs SET number = numbered.rn::text
            FROM (
                SELECT id, ROW_NUMBER() OVER (PARTITION BY company_id ORDER BY created_at, id) AS rn
                FROM landed_costs
            ) numbered
            WHERE landed_costs.id = numbered.id
        ");

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE landed_costs DROP CONSTRAINT IF EXISTS landed_costs_status_check');
            DB::statement("ALTER TABLE landed_costs ADD CONSTRAINT landed_costs_status_check CHECK (status::text = ANY (ARRAY['draft'::character varying, 'allocated'::character varying, 'posted'::character varying, 'reversed'::character varying]::text[]))");
        }
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE landed_costs DROP CONSTRAINT IF EXISTS landed_costs_status_check');
            DB::statement("ALTER TABLE landed_costs ADD CONSTRAINT landed_costs_status_check CHECK (status::text = ANY (ARRAY['draft'::character varying, 'allocated'::character varying, 'posted'::character varying]::text[]))");
        }

        Schema::table('landed_costs', function (Blueprint $table) {
            $table->dropColumn('number');
        });
    }
};
