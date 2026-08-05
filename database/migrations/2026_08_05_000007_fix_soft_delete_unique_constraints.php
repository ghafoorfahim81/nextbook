<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Repair uniqueness across the reference tables.
 *
 * Every one of these declares `unique([..., 'deleted_at'])`, which does not
 * work in PostgreSQL: NULL != NULL, and deleted_at is NULL on every live row,
 * so the constraint never fires. Duplicate item names, codes and category
 * names are all currently possible — and the matching validation rules use
 * `unique:items,name,NULL,id,branch_id,NULL,...`, which tests
 * `branch_id IS NULL` and never matches either, so duplicates escape both
 * layers.
 *
 * The fix is a partial unique index over live rows only.
 *
 * If this migration fails, the table already contains duplicates. The error
 * names the table and the offending values so they can be merged first.
 */
return new class extends Migration
{
    /** table => [index suffix => columns] */
    private const TARGETS = [
        'items' => [
            'name' => ['branch_id', 'name'],
            'code' => ['branch_id', 'code'],
        ],
        'categories'    => ['name' => ['branch_id', 'name']],
        'brands'        => ['name' => ['branch_id', 'name']],
        'warehouses'    => ['name' => ['branch_id', 'name']],
        'sizes'         => ['name' => ['branch_id', 'name'], 'code' => ['branch_id', 'code']],
        'unit_measures' => ['name' => ['branch_id', 'name']],
    ];

    /** Nullable columns: only unique when a value is actually present. */
    private const NULLABLE_TARGETS = [
        'items' => ['barcode' => ['branch_id', 'barcode']],
    ];

    public function up(): void
    {
        foreach (self::TARGETS as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $suffix => $columns) {
                $this->guardAgainstDuplicates($table, $columns);
                $this->dropLegacyUnique($table, $columns);

                $name = "{$table}_{$suffix}_live_unique";
                $cols = implode(', ', $columns);

                DB::statement("CREATE UNIQUE INDEX {$name} ON {$table} ({$cols})
                    WHERE deleted_at IS NULL");
            }
        }

        foreach (self::NULLABLE_TARGETS as $table => $indexes) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            foreach ($indexes as $suffix => $columns) {
                $this->dropLegacyUnique($table, $columns);

                $name = "{$table}_{$suffix}_live_unique";
                $cols = implode(', ', $columns);
                $valueColumn = $columns[count($columns) - 1];

                DB::statement("CREATE UNIQUE INDEX {$name} ON {$table} ({$cols})
                    WHERE deleted_at IS NULL AND {$valueColumn} IS NOT NULL");
            }
        }
    }

    public function down(): void
    {
        foreach (array_merge_recursive(self::TARGETS, self::NULLABLE_TARGETS) as $table => $indexes) {
            foreach (array_keys($indexes) as $suffix) {
                DB::statement("DROP INDEX IF EXISTS {$table}_{$suffix}_live_unique");
            }
        }
    }

    /**
     * Fail with something readable rather than a bare 23505 from Postgres.
     */
    private function guardAgainstDuplicates(string $table, array $columns): void
    {
        $cols = implode(', ', $columns);

        $duplicates = DB::select("
            SELECT {$cols}, COUNT(*) AS total
            FROM {$table}
            WHERE deleted_at IS NULL
            GROUP BY {$cols}
            HAVING COUNT(*) > 1
            LIMIT 10
        ");

        if (empty($duplicates)) {
            return;
        }

        $detail = collect($duplicates)
            ->map(fn ($row) => implode(' / ', array_map(
                fn ($c) => (string) $row->{$c},
                $columns
            )) . " (x{$row->total})")
            ->implode('; ');

        throw new RuntimeException(
            "Cannot add unique index on {$table} ({$cols}): duplicate live rows exist. "
            . "Merge or soft-delete these first: {$detail}"
        );
    }

    private function dropLegacyUnique(string $table, array $columns): void
    {
        $legacy = $table . '_' . implode('_', $columns) . '_deleted_at_unique';
        DB::statement("ALTER TABLE {$table} DROP CONSTRAINT IF EXISTS {$legacy}");
        DB::statement("DROP INDEX IF EXISTS {$legacy}");
    }
};
