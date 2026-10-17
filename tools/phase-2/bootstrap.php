<?php

/**
 * Shared setup for the phase 2 helper scripts.
 *
 * These are one-off refactoring tools, not application code. They are kept in
 * the repository so the migration to company scoping can be resumed on another
 * machine — see docs/phase-2-handoff.md.
 *
 * Run them from the project root:
 *
 *     php tools/phase-2/scan.php
 */

define('REPO', str_replace('\\', '/', dirname(__DIR__, 2)));

/** An absolute path inside the repository. */
function repo(string $relative = ''): string
{
    return $relative === '' ? REPO : REPO . '/' . ltrim($relative, '/');
}

/** Strip the repository prefix for display. */
function rel(string $path): string
{
    $path = str_replace('\\', '/', $path);

    return str_starts_with($path, REPO . '/')
        ? substr($path, strlen(REPO) + 1)
        : $path;
}

/** Every .php file under a directory, sorted. */
function phpFiles(string $dir): array
{
    if (! is_dir($dir)) {
        return [];
    }

    $files = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($it as $f) {
        if ($f->getExtension() === 'php') {
            $files[] = $f->getPathname();
        }
    }
    sort($files);

    return $files;
}

/**
 * Database credentials from the project's .env, so nothing is hardcoded and
 * these work unchanged on another machine.
 */
function envDb(): array
{
    $env = [];
    foreach (file(repo('.env'), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        if (str_starts_with(trim($line), '#') || ! str_contains($line, '=')) {
            continue;
        }
        [$k, $v] = explode('=', $line, 2);
        $env[trim($k)] = trim($v, " \t\"'");
    }

    return [
        'host' => $env['DB_HOST'] ?? '127.0.0.1',
        'port' => $env['DB_PORT'] ?? '5432',
        'database' => $env['DB_DATABASE'] ?? 'nextbook',
        'username' => $env['DB_USERNAME'] ?? 'postgres',
        'password' => $env['DB_PASSWORD'] ?? '',
    ];
}
