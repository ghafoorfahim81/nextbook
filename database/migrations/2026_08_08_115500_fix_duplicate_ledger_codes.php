<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Assign new sequential codes to duplicate active ledgers, then enforce uniqueness.
     */
    public function up(): void
    {
        DB::unprepared(<<<'SQL'
            DO $$
            DECLARE
                duplicate_ledger RECORD;
                next_number INTEGER;
                prefix TEXT;
            BEGIN
                FOR duplicate_ledger IN
                    SELECT id, branch_id, type
                    FROM (
                        SELECT
                            id,
                            branch_id,
                            type,
                            code,
                            ROW_NUMBER() OVER (
                                PARTITION BY branch_id, code
                                ORDER BY created_at, id
                            ) AS duplicate_rank
                        FROM ledgers
                        WHERE type IN ('customer', 'supplier')
                          AND deleted_at IS NULL
                    ) AS duplicate_codes
                    WHERE duplicate_rank > 1
                LOOP
                    prefix := CASE
                        WHEN duplicate_ledger.type = 'customer' THEN 'CUST-'
                        ELSE 'SUP-'
                    END;

                    SELECT COALESCE(
                        MAX(CAST(REGEXP_REPLACE(code, '^(CUST-|SUP-)', '') AS INTEGER)),
                        0
                    ) + 1
                    INTO next_number
                    FROM ledgers
                    WHERE type = duplicate_ledger.type
                      AND branch_id IS NOT DISTINCT FROM duplicate_ledger.branch_id
                      AND deleted_at IS NULL
                      AND code ~ '^(CUST-|SUP-)?[0-9]+$';

                    UPDATE ledgers
                    SET code = prefix || LPAD(next_number::TEXT, 3, '0')
                    WHERE id = duplicate_ledger.id;
                END LOOP;
            END $$;
        SQL);

        DB::statement(
            'CREATE UNIQUE INDEX ledgers_branch_code_active_unique
            ON ledgers (branch_id, code)
            WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS ledgers_branch_code_active_unique');
    }
};
