<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE landed_costs DROP CONSTRAINT IF EXISTS landed_costs_allocation_method_check');
        DB::statement("ALTER TABLE landed_costs ADD CONSTRAINT landed_costs_allocation_method_check CHECK (allocation_method::text = ANY (ARRAY['by_value'::character varying, 'by_quantity'::character varying, 'by_weight'::character varying, 'by_volume'::character varying, 'manual'::character varying]::text[]))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE landed_costs DROP CONSTRAINT IF EXISTS landed_costs_allocation_method_check');
        DB::statement("ALTER TABLE landed_costs ADD CONSTRAINT landed_costs_allocation_method_check CHECK (allocation_method::text = ANY (ARRAY['by_value'::character varying, 'by_quantity'::character varying, 'by_weight'::character varying, 'by_volume'::character varying]::text[]))");
    }
};
