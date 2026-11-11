<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * A person can be both a customer and a supplier. They are two ledgers, so the
 * name (and phone/email, which the same person naturally shares) must only be
 * unique among ledgers of the same type.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('DROP INDEX IF EXISTS ledgers_company_id_name_live_unique');
        DB::statement('DROP INDEX IF EXISTS ledgers_company_id_phone_no_live_unique');
        DB::statement('DROP INDEX IF EXISTS ledgers_company_id_email_live_unique');

        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_type_name_live_unique ON ledgers (company_id, type, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_type_phone_no_live_unique ON ledgers (company_id, type, phone_no) WHERE deleted_at IS NULL AND phone_no IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_type_email_live_unique ON ledgers (company_id, type, email) WHERE deleted_at IS NULL AND email IS NOT NULL');
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ledgers_company_id_type_name_live_unique');
        DB::statement('DROP INDEX IF EXISTS ledgers_company_id_type_phone_no_live_unique');
        DB::statement('DROP INDEX IF EXISTS ledgers_company_id_type_email_live_unique');

        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_name_live_unique ON ledgers (company_id, name) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_phone_no_live_unique ON ledgers (company_id, phone_no) WHERE deleted_at IS NULL AND phone_no IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX ledgers_company_id_email_live_unique ON ledgers (company_id, email) WHERE deleted_at IS NULL AND email IS NOT NULL');
    }
};
