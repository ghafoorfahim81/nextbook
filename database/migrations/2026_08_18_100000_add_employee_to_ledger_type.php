<?php

use App\Enums\LedgerType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Widen the ledgers.type CHECK constraint to admit 'employee'.
 *
 * Laravel's $table->enum() on Postgres emits a varchar column plus a CHECK
 * constraint listing the allowed values; there is no native enum type to ALTER.
 * The constraint is conventionally named <table>_<column>_check, but that is a
 * convention and not a guarantee — a table rebuilt by an earlier migration can
 * carry a suffixed name. So the name is looked up from the catalog rather than
 * hardcoded, and dropping is a no-op when nothing matches.
 */
return new class extends Migration
{
    private const ALLOWED_UP = ['customer', 'supplier', 'employee'];

    private const ALLOWED_DOWN = ['customer', 'supplier'];

    public function up(): void
    {
        $this->rebuildTypeCheck(self::ALLOWED_UP);
    }

    public function down(): void
    {
        // Employee ledgers would violate the narrowed constraint, so they have
        // to go first. They are recreated from `employees` by EmployeeObserver
        // if this migration is ever re-applied.
        DB::table('ledgers')->where('type', LedgerType::EMPLOYEE->value)->delete();

        $this->rebuildTypeCheck(self::ALLOWED_DOWN);
    }

    private function rebuildTypeCheck(array $allowed): void
    {
        if (! $this->isPostgres()) {
            // MySQL/SQLite store enum() differently and no environment here uses
            // them; leaving the column untouched is safer than guessing.
            return;
        }

        foreach ($this->typeCheckConstraintNames() as $name) {
            DB::statement(sprintf(
                'ALTER TABLE ledgers DROP CONSTRAINT %s',
                $this->quoteIdentifier($name)
            ));
        }

        $values = implode(', ', array_map(fn (string $v) => "'".str_replace("'", "''", $v)."'", $allowed));

        DB::statement(sprintf(
            'ALTER TABLE ledgers ADD CONSTRAINT ledgers_type_check CHECK (type::text = ANY (ARRAY[%s]::text[]))',
            $values
        ));
    }

    /**
     * Every CHECK constraint on ledgers that references the `type` column.
     *
     * Matching on the referenced column rather than the name is what makes this
     * safe against a non-conventional constraint name.
     */
    private function typeCheckConstraintNames(): array
    {
        return array_map(
            static fn (object $row) => $row->conname,
            DB::select(<<<'SQL'
                SELECT con.conname
                FROM pg_constraint con
                JOIN pg_class rel ON rel.oid = con.conrelid
                JOIN pg_namespace ns ON ns.oid = rel.relnamespace
                WHERE rel.relname = 'ledgers'
                  AND ns.nspname = current_schema()
                  AND con.contype = 'c'
                  AND EXISTS (
                      SELECT 1
                      FROM pg_attribute att
                      WHERE att.attrelid = con.conrelid
                        AND att.attnum = ANY (con.conkey)
                        AND att.attname = 'type'
                  )
            SQL)
        );
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }

    private function quoteIdentifier(string $identifier): string
    {
        return '"'.str_replace('"', '""', $identifier).'"';
    }
};
