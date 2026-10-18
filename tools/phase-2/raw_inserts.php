<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Raw inserts that set branch_id but not company_id.
 *
 * `DB::table(...)->insert([...])` bypasses the model entirely, so neither
 * HasCompany nor HasCompanyFromParent runs and the NOT NULL constraint is the
 * first thing to notice. Every one of these needs the company written
 * alongside the branch.
 *
 *     php tools/phase-2/raw_inserts.php
 *     php tools/phase-2/raw_inserts.php --fix
 */

$fix = in_array('--fix', $argv, true);
$hits = [];

foreach (['app', 'tests', 'database'] as $root) {
    foreach (phpFiles(repo($root)) as $path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $dirty = false;
        $out = [];

        foreach ($lines as $i => $line) {
            $out[] = $line;

            if (! preg_match("/^(\s*)'branch_id'\s*=>\s*(.+?),\s*$/", $line, $m)) {
                continue;
            }

            // Only inside a raw insert/update — a model create fills the
            // company itself, and a log context is not a row at all.
            $raw = false;
            for ($j = $i; $j >= max(0, $i - 20); $j--) {
                if (preg_match('/Log::|logger\(|->log\(/', $lines[$j])) {
                    break;
                }
                if (preg_match('/DB::table\(|->insert\(|->insertOrIgnore\(|->updateOrInsert\(/', $lines[$j])) {
                    $raw = true;
                    break;
                }
                if (preg_match('/::create\(|::factory\(/', $lines[$j])) {
                    break;
                }
            }
            if (! $raw) {
                continue;
            }

            // Already paired?
            $near = implode("\n", array_slice($lines, max(0, $i - 12), 24));
            if (str_contains($near, "'company_id'")) {
                continue;
            }

            $hits[] = rel($path) . ':' . ($i + 1) . '  ' . trim($line);

            if ($fix) {
                $value = $m[2];

                // Inside a test the context already holds both levels, which
                // reads better than a sub-query; elsewhere the branch resolves
                // the company it belongs to.
                $company = preg_replace(
                    "/\['branch'\]/",
                    "['company']",
                    $value,
                    1,
                    $replaced
                );

                if (! $replaced) {
                    $company = "\\App\\Models\\Administration\\Branch::withoutGlobalScopes()"
                        . "->whereKey({$value})->value('company_id')";
                }

                $out[] = $m[1] . "'company_id' => {$company},";
                $dirty = true;
            }
        }

        if ($dirty) {
            file_put_contents($path, implode("\n", $out) . "\n");
        }
    }
}

echo ($fix ? 'fixed ' : 'found ') . count($hits) . ":\n  " . implode("\n  ", $hits) . "\n";
