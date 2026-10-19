<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Create (or drop) a throwaway database for verifying the migration chain.
 *
 * The phase 2 work is verified by building the schema from scratch rather than
 * against a development database that already holds data. Credentials come from
 * the project's .env, so nothing is hardcoded.
 *
 *     php tools/phase-2/mkdb.php                   # recreate nextbook_phase2_check
 *     php tools/phase-2/mkdb.php my_db             # recreate my_db
 *     php tools/phase-2/mkdb.php my_db drop        # drop it
 *
 * Then point artisan at it:
 *
 *     DB_DATABASE=nextbook_phase2_check php artisan migrate --force
 */
$name = $argv[1] ?? 'nextbook_phase2_check';
$drop = ($argv[2] ?? '') === 'drop';

$db = envDb();

$pdo = new PDO(
    "pgsql:host={$db['host']};port={$db['port']};dbname=postgres",
    $db['username'],
    $db['password'],
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

$pdo->exec("DROP DATABASE IF EXISTS \"$name\" WITH (FORCE)");
echo "dropped $name\n";

if (! $drop) {
    $pdo->exec("CREATE DATABASE \"$name\"");
    echo "created $name\n";
}
