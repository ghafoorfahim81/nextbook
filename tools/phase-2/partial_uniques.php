<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Step 8: rewrite `unique([..., 'deleted_at'])` as partial unique indexes.
 *
 * NULL never equals NULL in PostgreSQL and deleted_at is NULL on every live
 * row, so a unique key that includes deleted_at never fires. The working form
 * is a unique index over the other columns, restricted to live rows:
 *
 *     CREATE UNIQUE INDEX <name> ON <table> (<cols>) WHERE deleted_at IS NULL
 *
 * with `AND <col> IS NOT NULL` when the value column is optional.
 *
 * Only the entries in TARGETS are touched. Each was read off the live schema
 * (pg_index), so a unique that a later repair migration already replaces is
 * not on the list and stays as it is. Anything else that still matches the
 * broken shape is reported.
 *
 * The statement is emitted right after the Schema::create / Schema::table call
 * that held the broken key, so the index exists from the moment the table does.
 *
 * Usage: php tools/phase-2/partial_uniques.php [--dry]
 */

/**
 * "table:old,columns" => [new columns, index name, nullable value column?]
 *
 * A null new-column list means: drop the broken key and create nothing, because
 * a later migration already builds the live version.
 */
const TARGETS = [
    // Master data, already company-scoped.
    'account_types:company_id,name' => [['company_id', 'name'], null, false],
    'account_types:company_id,slug' => [['company_id', 'slug'], null, true],
    'accounts:company_id,number' => [['company_id', 'number'], null, false],
    'accounts:company_id,name' => [['company_id', 'name'], null, false],
    'accounts:company_id,slug' => [['company_id', 'slug'], null, true],
    'currencies:company_id,name' => [['company_id', 'name'], null, false],
    'currencies:company_id,code' => [['company_id', 'code'], null, false],
    'currencies:company_id,flag' => [['company_id', 'flag'], null, true],
    'customer_groups:company_id,name_en' => [['company_id', 'name_en'], null, false],
    'departments:company_id,name' => [['company_id', 'name'], null, false],
    'departments:company_id,code' => [['company_id', 'code'], null, false],
    'designations:company_id,name' => [['company_id', 'name'], null, false],
    'designations:company_id,code' => [['company_id', 'code'], null, true],
    'expense_categories:company_id,name' => [['company_id', 'name'], null, false],
    'holidays:company_id,date,name' => [['company_id', 'date', 'name'], null, false],
    'hr_settings:company_id' => [['company_id'], null, false],
    'landed_cost_categories:company_id,name' => [['company_id', 'name'], null, false],
    'leave_types:company_id,code' => [['company_id', 'code'], null, false],
    'ledgers:company_id,name' => [['company_id', 'name'], null, false],
    // ledgers_branch_code_active_unique (fix_duplicate_ledger_codes) is the
    // live version of this one.
    'ledgers:company_id,code' => [null, null, false],
    'ledgers:company_id,phone_no' => [['company_id', 'phone_no'], null, true],
    'ledgers:company_id,email' => [['company_id', 'email'], null, true],
    'owners:company_id,name' => [['company_id', 'name'], null, false],
    'owners:company_id,nic' => [['company_id', 'nic'], null, true],
    'owners:company_id,email' => [['company_id', 'email'], null, true],
    'owners:company_id,phone_number' => [['company_id', 'phone_number'], null, true],
    'payment_terms:company_id,name' => [['company_id', 'name'], null, false],
    'quantities:company_id,slug' => [['company_id', 'slug'], null, false],
    'salary_components:company_id,code' => [['company_id', 'code'], null, false],
    'shifts:company_id,code' => [['company_id', 'code'], null, false],
    'employee_contracts:company_id,contract_number' => [['company_id', 'contract_number'], null, false],

    // Document numbers: one series per company and document type.
    'payrolls:branch_id,number' => [['company_id', 'number'], null, false],
    'employee_loans:branch_id,number' => [['company_id', 'number'], null, false],
    'salary_payments:branch_id,number' => [['company_id', 'number'], null, false],
    'leave_requests:branch_id,number' => [['company_id', 'number'], null, false],

    // One person, one login, across the whole database.
    'users:branch_id,username' => [['username'], null, true],
    'users:branch_id,email' => [['email'], null, false],

    // One person is hired once per company.
    'employees:branch_id,national_id' => [['company_id', 'national_id'], null, true],

    // No scope decision in the plan: keep the branch, fix the NULL bug only.
    'employees:branch_id,code' => [['branch_id', 'code'], null, false],
    'employees:branch_id,user_id' => [['branch_id', 'user_id'], null, true],
    'attendance_devices:branch_id,code' => [['branch_id', 'code'], null, false],
    'attendance_device_users:branch_id,attendance_device_id,device_user_id' => [['branch_id', 'attendance_device_id', 'device_user_id'], 'adu_device_user_unique', false],
    'leave_allocations:branch_id,employee_id,leave_type_id,period_start' => [['branch_id', 'employee_id', 'leave_type_id', 'period_start'], 'leave_allocations_period_unique', false],

    // Keyed by parent, no scope column.
    'roles:slug' => [['slug'], null, true],
    'salary_payment_lines:salary_payment_id,payroll_line_id' => [['salary_payment_id', 'payroll_line_id'], 'salary_payment_line_unique', false],
    'salary_structure_lines:salary_structure_id,salary_component_id' => [['salary_structure_id', 'salary_component_id'], 'salary_structure_line_unique', false],
    'tax_brackets:tax_bracket_set_id,sequence' => [['tax_bracket_set_id', 'sequence'], 'tax_bracket_sequence_unique', false],
];

$dry = in_array('--dry', $argv, true);

/** [start, end] of the call whose opening paren follows $from, end past the ';'. */
function statementRange(string $src, int $from): array
{
    $open = strpos($src, '(', $from);
    $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
        if ($src[$i] === '(') {
            $depth++;
        } elseif ($src[$i] === ')') {
            $depth--;
            if ($depth === 0) {
                $semi = strpos($src, ';', $i);

                return [$from, $semi + 1];
            }
        }
    }

    return [$from, strlen($src)];
}

function indexName(string $table, array $columns): string
{
    $name = $table . '_' . implode('_', $columns) . '_live_unique';

    // PostgreSQL truncates identifiers at 63 bytes; truncation could make two
    // names collide silently, so shorten deliberately instead.
    return strlen($name) <= 63 ? $name : substr($table, 0, 20) . '_' . substr(md5($name), 0, 10) . '_live_unique';
}

$seen = [];
$changed = [];
$unlisted = [];

// Matches the one-line form and the wrapped one, where the column list and the
// index name each sit on their own line.
$unique = '/^[ \t]*\$table->unique\(\s*\[([^\]]*)\'deleted_at\'\](?:\s*,\s*\'([^\']+)\')?\s*\);[ \t]*\R/m';

foreach (phpFiles(repo('database/migrations')) as $path) {
    $src = file_get_contents($path);
    $original = $src;

    if (! preg_match_all('/Schema::(create|table)\(\'([a-z_]+)\'/', $src, $calls, PREG_OFFSET_CAPTURE)) {
        continue;
    }

    // Walk backwards so earlier offsets stay valid while later text changes.
    for ($k = count($calls[0]) - 1; $k >= 0; $k--) {
        $table = $calls[2][$k][0];
        [$s, $e] = statementRange($src, $calls[0][$k][1]);
        $block = substr($src, $s, $e - $s);
        $statements = [];

        $block = preg_replace_callback($unique, function ($m) use ($table, $path, &$statements, &$seen, &$unlisted) {
            $cols = array_map(fn ($c) => trim($c, " '\t\n"), array_filter(explode(',', $m[1]), fn ($c) => trim($c) !== ''));
            $key = $table . ':' . implode(',', $cols);

            if (! array_key_exists($key, TARGETS)) {
                $unlisted[] = rel($path) . "  $key";

                return $m[0];
            }

            $seen[$key] = true;
            [$newCols, $name, $nullable] = TARGETS[$key];

            if ($newCols === null) {
                return '';
            }

            $name = $name ?? indexName($table, $newCols);
            $where = 'deleted_at IS NULL';
            if ($nullable) {
                $where .= ' AND ' . end($newCols) . ' IS NOT NULL';
            }

            $statements[] = sprintf(
                "        DB::statement('CREATE UNIQUE INDEX %s ON %s (%s) WHERE %s');",
                $name,
                $table,
                implode(', ', $newCols),
                $where
            );

            return '';
        }, $block);

        if ($statements !== []) {
            $block .= "\n\n        // Partial: unique([..., 'deleted_at']) never fires in PostgreSQL,\n"
                . "        // because deleted_at is NULL on every live row and NULL != NULL.\n"
                . implode("\n", $statements);
        }

        $src = substr($src, 0, $s) . $block . substr($src, $e);
    }

    if ($src !== $original) {
        if (! str_contains($src, 'use Illuminate\Support\Facades\DB;')) {
            $src = preg_replace('/^use Illuminate\\\\Support\\\\Facades\\\\Schema;/m', "use Illuminate\\Support\\Facades\\DB;\nuse Illuminate\\Support\\Facades\\Schema;", $src, 1);
        }

        $changed[] = rel($path);
        if (! $dry) {
            file_put_contents($path, $src);
        }
    }
}

echo ($dry ? 'WOULD CHANGE' : 'CHANGED') . ' (' . count($changed) . "):\n";
foreach ($changed as $c) {
    echo "  $c\n";
}

$missing = array_diff(array_keys(TARGETS), array_keys($seen));
echo "\nLISTED BUT NOT FOUND IN ANY MIGRATION (" . count($missing) . "):\n";
foreach ($missing as $m) {
    echo "  $m\n";
}

echo "\nBROKEN SHAPE, NOT LISTED — LEFT AS IS (" . count($unlisted) . "):\n";
foreach ($unlisted as $u) {
    echo "  $u\n";
}
