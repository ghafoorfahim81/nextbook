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

        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_status_check');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_status_check CHECK (status::text = ANY (ARRAY['posted'::character varying, 'approved'::character varying, 'rejected'::character varying, 'cancelled'::character varying, 'reversed'::character varying, 'draft'::character varying]::text[]))");
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('ALTER TABLE transactions DROP CONSTRAINT IF EXISTS transactions_status_check');
        DB::statement("ALTER TABLE transactions ADD CONSTRAINT transactions_status_check CHECK (status::text = ANY (ARRAY['posted'::character varying, 'approved'::character varying, 'rejected'::character varying, 'cancelled'::character varying, 'reversed'::character varying]::text[]))");
    }
};
