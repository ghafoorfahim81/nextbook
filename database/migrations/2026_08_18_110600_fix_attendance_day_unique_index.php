<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Make one-row-per-employee-per-day actually enforced.
 *
 * Same defect as the punch dedupe index: UNIQUE (branch_id, employee_id, date,
 * deleted_at) does not constrain live rows, because Postgres compares NULLs as
 * DISTINCT — two rows with deleted_at IS NULL and the same employee and date
 * are both accepted.
 *
 * That matters more here than almost anywhere else in the module. Attendance is
 * written by three independent paths (roster grid, punch pairing, leave
 * approval), each using updateOrCreate — a SELECT followed by an INSERT. With
 * no real constraint underneath, two of those running concurrently for the same
 * employee and day both find nothing and both insert, and every attendance
 * total for that person is then ambiguous.
 *
 * NOTE: the wider `unique([..., 'deleted_at'])` pattern used across this
 * codebase has the same shortfall. Elsewhere it is covered by request-level
 * validation (see App\Http\Requests\Concerns\BranchScopedUnique); the two HR
 * tables fixed here are written in bulk with no per-row validation, so the
 * index has to carry the guarantee itself.
 */
return new class extends Migration
{
    private const OLD_INDEX = 'attendances_employee_date_unique';

    private const NEW_INDEX = 'attendances_live_employee_date_unique';

    public function up(): void
    {
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Collapse anything the broken index already let through, keeping the
        // earliest row for each employee/day.
        DB::statement(<<<'SQL'
            DELETE FROM attendances a
            USING attendances keep
            WHERE a.branch_id = keep.branch_id
              AND a.employee_id = keep.employee_id
              AND a.date = keep.date
              AND a.deleted_at IS NULL
              AND keep.deleted_at IS NULL
              AND a.created_at > keep.created_at
        SQL);

        // Blueprint's unique() creates a constraint that owns its index, so the
        // constraint has to be dropped before the index will go.
        DB::statement('ALTER TABLE attendances DROP CONSTRAINT IF EXISTS '.self::OLD_INDEX);
        DB::statement('DROP INDEX IF EXISTS '.self::OLD_INDEX);

        DB::statement(
            'CREATE UNIQUE INDEX '.self::NEW_INDEX.
            ' ON attendances (branch_id, employee_id, date) WHERE deleted_at IS NULL'
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
            ' ON attendances (branch_id, employee_id, date, deleted_at)'
        );
    }
};
