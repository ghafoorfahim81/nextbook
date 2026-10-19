<?php

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/targets.php';

/**
 * Add company_id to every table that still lacks it.
 *
 * Additive only — branch_id stays where it is. Adding and removing the scope
 * column in one pass leaves master data and documents unjoinable in between,
 * which is what left the suite red after step 4. Dropping branch_id is a
 * separate pass once the queries have moved across.
 *
 *     php tools/phase-2/add_company_column.php --dry
 *     php tools/phase-2/add_company_column.php
 */

$dry = in_array('--dry', $argv, true);

/** Tables another table points a foreign key at — these need UNIQUE (company_id, id). */
function fkTargets(): array
{
    $targets = [];
    foreach (phpFiles(repo('database/migrations')) as $path) {
        $src = file_get_contents($path);
        preg_match_all("/->on\('([a-z_]+)'\)/", $src, $m);
        foreach ($m[1] as $t) {
            $targets[$t] = true;
        }
    }

    return $targets;
}

/** [start, end] offsets of the block whose opening paren follows $from. */
function blockRange(string $src, int $from): array
{
    $open = strpos($src, '(', $from);
    $depth = 0;
    for ($i = $open; $i < strlen($src); $i++) {
        if ($src[$i] === '(') {
            $depth++;
        } elseif ($src[$i] === ')') {
            $depth--;
            if ($depth === 0) {
                return [$from, $i + 1];
            }
        }
    }

    return [$from, strlen($src)];
}

$targets = fkTargets();
$todo = [];
foreach (appTables() as $t) {
    if (! hasCompanyColumn($t)) {
        $todo[] = $t;
    }
}

$report = [];

foreach (phpFiles(repo('database/migrations')) as $path) {
    $src = file_get_contents($path);
    $original = $src;

    foreach ($todo as $table) {
        $needle = "Schema::create('{$table}'";
        $pos = strpos($src, $needle);
        if ($pos === false) {
            continue;
        }

        [$s, $e] = blockRange($src, $pos);
        $block = substr($src, $s, $e - $s);

        if (str_contains($block, 'company_id')) {
            continue;
        }

        // The column goes immediately below the primary key so the scope is
        // the first thing read on the table.
        $block = preg_replace(
            '/(\n(\s*)\$table->ulid\(\'id\'\)->primary\(\);)/',
            "$1\n$2\$table->ulid('company_id')->index();",
            $block,
            1
        );

        if (! str_contains($block, "ulid('company_id')")) {
            $report[] = sprintf('%-32s %-52s SKIPPED (no ulid id primary key)', $table, basename($path));
            continue;
        }

        // Constraints at the end of the block.
        $extra = "";
        if (isset($targets[$table])) {
            $extra .= "\n            \$table->unique(['company_id', 'id']);";
        }
        $extra .= "\n            \$table->foreign('company_id')->references('id')->on('companies');";

        $block = preg_replace('/\n(\s*)\}\)\;$/', $extra . "\n$1});", rtrim($block), 1);

        $src = substr($src, 0, $s) . $block . substr($src, $e);
        $report[] = sprintf(
            '%-32s %-52s %s',
            $table,
            basename($path),
            isset($targets[$table]) ? '+unique(company_id,id)' : ''
        );
    }

    if ($src !== $original && ! $dry) {
        file_put_contents($path, $src);
    }
}

echo implode("\n", $report) . "\n\n";
echo count($report) . ' tables' . ($dry ? ' (dry run)' : '') . "\n";
