<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make the punch dedupe guarantee real.
 *
 * The original index was UNIQUE (branch_id, fingerprint, deleted_at), following
 * the branch-scoped-unique pattern used across this codebase. That pattern does
 * not actually enforce uniqueness in Postgres: NULLs compare as DISTINCT in a
 * unique index, so two live rows (deleted_at IS NULL) with the same fingerprint
 * are accepted. Everywhere else the shortfall is covered by request-level
 * validation, but punches arrive through a bulk insertOrIgnore with no
 * per-row validation — the index IS the dedupe, so it has to hold.
 *
 * A partial unique index over live rows only gives a true guarantee while still
 * allowing a soft-deleted punch to be re-imported.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'attendance_punches_fingerprint_unique';

    private const NEW_INDEX = 'attendance_punches_live_fingerprint_unique';

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Collapse any duplicates the broken index already let through, keeping
        // the earliest of each set.
        DB::statement(<<<'SQL'
            DELETE FROM attendance_punches ap
            USING attendance_punches keep
            WHERE ap.branch_id = keep.branch_id
              AND ap.fingerprint = keep.fingerprint
              AND ap.deleted_at IS NULL
              AND keep.deleted_at IS NULL
              AND ap.created_at > keep.created_at
        SQL);

        // Blueprint's unique() creates a CONSTRAINT backed by an index, and
        // Postgres refuses to drop the index while the constraint owns it —
        // so the constraint has to go first.
        DB::statement('ALTER TABLE attendance_punches DROP CONSTRAINT IF EXISTS '.self::OLD_INDEX);
        DB::statement('DROP INDEX IF EXISTS '.self::OLD_INDEX);

        DB::statement(
            'CREATE UNIQUE INDEX '.self::NEW_INDEX.
            ' ON attendance_punches (branch_id, fingerprint) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS '.self::NEW_INDEX);

        DB::statement(
            'CREATE UNIQUE INDEX '.self::OLD_INDEX.
            ' ON attendance_punches (branch_id, fingerprint, deleted_at)'
        );
    }
};
