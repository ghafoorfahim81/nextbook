<?php

require_once __DIR__ . '/bootstrap.php';
/**
 * Category A: move the 30 master-data tables from branch scope to company scope.
 *
 * Operates on Schema::create('<t>', ...) / Schema::table('<t>', ...) BLOCKS, not
 * whole files. Three migrations create several tables at once and some of their
 * siblings are branch-scoped documents that must keep branch_id — e.g. the leave
 * migration creates leave_types (company) next to leave_requests (branch).
 */

const TABLES = [
    'account_types','accounts','items','item_variants','brands','categories',
    'unit_measures','quantities','sizes','currencies','ledgers','customer_groups',
    'payment_terms','discount_rules','expense_categories','journal_classes',
    'landed_cost_categories','financial_periods','owners','departments',
    'designations','shifts','leave_types','holidays','hr_settings',
    'salary_components','salary_structures','salary_structure_lines',
    'tax_bracket_sets','tax_brackets',
];

$dry = in_array('--dry', $argv, true);
$root = repo('database/migrations');

/** Return [start, end] offsets of the block whose opening paren follows $from. */
function blockRange(string $src, int $from): array
{
    $open = strpos($src, '(', $from);
    $depth = 0;
    for ($i = $open; $i < strlen($src); $i++) {
        if ($src[$i] === '(') $depth++;
        elseif ($src[$i] === ')') {
            $depth--;
            if ($depth === 0) return [$from, $i + 1];
        }
    }
    return [$from, strlen($src)];
}

$files = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
foreach ($it as $f) if ($f->getExtension() === 'php') $files[] = $f->getPathname();
sort($files);

$report = [];

foreach ($files as $path) {
    $src = file_get_contents($path);
    $original = $src;

    foreach (TABLES as $table) {
        foreach (['create', 'table'] as $kind) {
            $needle = "Schema::{$kind}('{$table}'";
            $offset = 0;

            while (($pos = strpos($src, $needle, $offset)) !== false) {
                [$s, $e] = blockRange($src, $pos);
                $block = substr($src, $s, $e - $s);
                $before = $block;

                // Column definition.
                $block = preg_replace(
                    "/->ulid\('branch_id'\)/",
                    "->ulid('company_id')",
                    $block
                );
                $block = preg_replace(
                    "/->foreignUlid\('branch_id'\)/",
                    "->foreignUlid('company_id')",
                    $block
                );

                // Unique / index column lists and foreign() references.
                $block = str_replace("'branch_id'", "'company_id'", $block);

                // Retarget the foreign key itself: companies, not branches.
                $block = preg_replace(
                    "/(foreign\('company_id'\)\s*(?:->\s*references\('id'\)\s*)?->\s*on\(')branches('\))/s",
                    '${1}companies${2}',
                    $block
                );

                // `(company_id, id)` is the target every child table's composite
                // foreign key points at. Postgres needs a unique index on exactly
                // that column list before such a key can exist.
                if ($kind === 'create' && str_contains($block, "'company_id'")
                    && ! str_contains($block, "['company_id', 'id']")) {
                    $block = preg_replace(
                        '/(\n(\s*)\$table->timestamps\(\);)/',
                        "\n\${2}\$table->unique(['company_id', 'id']);\${1}",
                        $block,
                        1
                    );
                }

                if ($block !== $before) {
                    $report[] = sprintf('%-28s %s (%s)', $table, basename($path), $kind);
                }

                $src = substr($src, 0, $s) . $block . substr($src, $e);
                $offset = $s + strlen($block);
            }
        }
    }

    if ($src !== $original && ! $dry) {
        file_put_contents($path, $src);
    }
}

echo implode("\n", $report) . "\n\n";
echo count($report) . " blocks changed" . ($dry ? ' (dry run)' : '') . "\n";
