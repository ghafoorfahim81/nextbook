<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Which tables still need a company_id, computed rather than transcribed.
 *
 * Everything the application owns is company-scoped except the root of the tree
 * and the few rows that hang off a user rather than a company. Deriving the
 * list from the schema avoids the transcription errors a hand-kept list of a
 * hundred names collects.
 */

/** Framework-owned tables and reference data shared across every company. */
const NOT_SCOPED = [
    'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'sessions',
    'password_reset_tokens', 'personal_access_tokens', 'notifications',
    'countries', 'provinces', 'migrations',
    // The organisational tree itself.
    'companies', 'branches',
    // Hang off a user, not a company.
    'favorites', 'password_histories',
];

function appTables(): array
{
    $tables = [];
    foreach (phpFiles(repo('database/migrations')) as $path) {
        $src = file_get_contents($path);
        preg_match_all("/Schema::create\('([a-z_]+)'/", $src, $m);
        foreach ($m[1] as $t) {
            $tables[$t] = true;
        }
    }

    // Dropped during phase 2.
    unset($tables['transaction_line_currencies']);

    foreach (NOT_SCOPED as $t) {
        unset($tables[$t]);
    }

    ksort($tables);

    return array_keys($tables);
}

/** Does any create/table block for this table already define company_id? */
function hasCompanyColumn(string $table): bool
{
    foreach (phpFiles(repo('database/migrations')) as $path) {
        $src = file_get_contents($path);
        if (! preg_match("/Schema::(?:create|table)\('$table'/", $src)) {
            continue;
        }
        // Narrow to this table's blocks.
        $offset = 0;
        while (preg_match("/Schema::(?:create|table)\('$table'/", $src, $m, PREG_OFFSET_CAPTURE, $offset)) {
            $start = $m[0][1];
            $next = strlen($src);
            if (preg_match('/Schema::(?:create|table)\(/', $src, $n, PREG_OFFSET_CAPTURE, $start + 10)) {
                $next = $n[0][1];
            }
            $block = substr($src, $start, $next - $start);
            if (str_contains($block, 'company_id')) {
                return true;
            }
            $offset = $start + 10;
        }
    }

    return false;
}

if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === 'targets.php') {
    $done = $todo = [];
    foreach (appTables() as $t) {
        if (hasCompanyColumn($t)) {
            $done[] = $t;
        } else {
            $todo[] = $t;
        }
    }

    echo 'ALREADY SCOPED (' . count($done) . "):\n  " . implode(', ', $done) . "\n\n";
    echo 'STILL NEEDS company_id (' . count($todo) . "):\n  " . implode(', ', $todo) . "\n";
}
