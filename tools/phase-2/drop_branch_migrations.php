<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Step 7: take branch_id off tables whose branch is derivable.
 *
 * Category C (document lines and child records) gets its branch from the
 * parent document; category D (stock) gets it from the warehouse. Either way
 * the column only repeats a fact stored elsewhere, and nothing keeps the copy
 * in step with the original.
 *
 * Like rename_migrations.php this works on Schema::create / Schema::table
 * BLOCKS, because several migrations create a document and its lines side by
 * side and the document keeps its branch.
 *
 * Inside each block:
 *   $table->ulid('branch_id')...;              removed
 *   $table->foreign('branch_id')...;           removed
 *   $table->index(['branch_id', ...]);         company_id takes its place, so
 *                                              the lookup keeps an index led by
 *                                              the scope column (classification
 *                                              doc, section 3)
 *
 * Anything else mentioning branch_id in a block is reported, not touched.
 *
 * Usage: php tools/phase-2/drop_branch_migrations.php [--dry] table [table ...]
 */

$args = array_slice($argv, 1);
$dry = in_array('--dry', $args, true);
$tables = array_values(array_filter($args, fn ($a) => $a !== '--dry'));

if ($tables === []) {
    fwrite(STDERR, "usage: drop_branch_migrations.php [--dry] table [table ...]\n");
    exit(1);
}

/** [start, end] of the call whose opening paren follows $from. */
function callRange(string $src, int $from): array
{
    $open = strpos($src, '(', $from);
    $depth = 0;
    for ($i = $open, $n = strlen($src); $i < $n; $i++) {
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

$changed = [];
$leftovers = [];

foreach (phpFiles(repo('database/migrations')) as $path) {
    $src = file_get_contents($path);
    $original = $src;

    foreach ($tables as $table) {
        foreach (['create', 'table'] as $kind) {
            $needle = "Schema::{$kind}('{$table}'";
            $offset = 0;

            while (($pos = strpos($src, $needle, $offset)) !== false) {
                [$s, $e] = callRange($src, $pos);
                $block = substr($src, $s, $e - $s);

                $new = preg_replace('/^[ \t]*\$table->ulid\(\'branch_id\'\)[^;]*;[ \t]*\R/m', '', $block);
                $new = preg_replace('/^[ \t]*\$table->foreign\(\'branch_id\'\)[^;]*;[ \t]*\R/m', '', $new);
                $new = preg_replace('/(\$table->index\(\[)\'branch_id\'/', '$1\'company_id\'', $new);

                foreach (preg_split('/\R/', $new) as $line) {
                    if (str_contains($line, 'branch_id')) {
                        $leftovers[] = sprintf('%s [%s]  %s', rel($path), $table, trim($line));
                    }
                }

                $src = substr($src, 0, $s) . $new . substr($src, $e);
                $offset = $s + strlen($new);
            }
        }
    }

    if ($src !== $original) {
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

echo "\nSTILL MENTIONS branch_id — CHECK BY HAND (" . count($leftovers) . "):\n";
foreach ($leftovers as $l) {
    echo "  $l\n";
}
