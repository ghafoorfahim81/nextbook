<?php

require_once __DIR__ . '/bootstrap.php';
// Maps each category A table to its migration, model file, and which branch
// trait the model uses — the input the rename script needs.
$tables = [
    'account_types','accounts','items','item_variants','brands','categories',
    'unit_measures','quantities','sizes','currencies','ledgers','customer_groups',
    'payment_terms','discount_rules','expense_categories','journal_classes',
    'landed_cost_categories','financial_periods','owners','departments',
    'designations','shifts','leave_types','holidays','hr_settings',
    'salary_components','salary_structures','salary_structure_lines',
    'tax_bracket_sets','tax_brackets',
];

// migration that creates each table
$creators = [];
$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(repo('database/migrations')));
foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $src = file_get_contents($f->getPathname());
    foreach ($tables as $t) {
        if (preg_match("/Schema::create\('$t'/", $src)) $creators[$t] = $f->getPathname();
    }
}

// model file per table
$models = [];
$it2 = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(repo('app/Models')));
foreach ($it2 as $f) {
    if ($f->getExtension() !== 'php') continue;
    $src = file_get_contents($f->getPathname());
    if (preg_match("/protected \\\$table\s*=\s*'([a-z_]+)'/", $src, $m)) {
        $models[$m[1]][] = $f->getPathname();
        continue;
    }
    // Fall back to Laravel's convention: StudlyClass -> snake plural
    $class = $f->getBasename('.php');
    $snake = strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $class));
    $guess = \Illuminate\Support\Str::class; // not loaded; do it manually
    foreach ($tables as $t) {
        // crude plural match
        if ($snake . 's' === $t || $snake === $t || $snake . 'es' === $t
            || preg_replace('/y$/', 'ies', $snake) === $t) {
            $models[$t][] = $f->getPathname();
        }
    }
}

$rel = fn($p) => rel($p ?? '');

printf("%-26s %-58s %-46s %s\n", 'TABLE', 'MIGRATION', 'MODEL', 'TRAITS');
foreach ($tables as $t) {
    $modelPaths = $models[$t] ?? [];
    $traits = [];
    foreach ($modelPaths as $mp) {
        $src = file_get_contents($mp);
        foreach (['BranchSpecific', 'BelongsToBranch', 'HasBranch'] as $tr) {
            if (preg_match('/\b' . $tr . '\b/', $src)) $traits[] = $tr;
        }
    }
    printf(
        "%-26s %-58s %-46s %s\n",
        $t,
        basename($creators[$t] ?? 'MISSING'),
        $modelPaths ? $rel($modelPaths[0]) : 'MISSING',
        implode(',', array_unique($traits)) ?: '-'
    );
}
