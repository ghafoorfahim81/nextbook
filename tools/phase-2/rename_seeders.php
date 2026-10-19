<?php

require_once __DIR__ . '/bootstrap.php';
/**
 * Point the category A seeders at the company instead of the main branch.
 *
 * They all follow the same shape — resolve the main branch, then stamp its id
 * on every default row. The scope moved up a level, so they resolve the company
 * instead. WarehouseSeeder is deliberately absent: a warehouse stays
 * branch-scoped.
 */
const SEEDERS = [
    'database\seeders\Account\AccountSeeder.php',
    'database\seeders\Account\AccountTypeSeeder.php',
    'database\seeders\Administration\CurrencySeeder.php',
    'database\seeders\Administration\SizeSeeder.php',
    'database\seeders\Administration\CategorySeeder.php',
    'database\seeders\Administration\UnitMeasureSeeder.php',
    'database\seeders\Administration\LandedCostCategorySeeder.php',
    'database\seeders\Ledger\LedgerSeeder.php',
    'database\seeders\Inventory\ItemSeeder.php',
];

$report = [];

foreach (SEEDERS as $rel) {
    $path = repo($rel);
    if (! is_file($path)) { $report[] = "MISSING  $rel"; continue; }

    $src = file_get_contents($path);
    $before = $src;

    // The "which branch do these defaults belong to" lookups.
    $src = preg_replace(
        '/Branch::(?:withoutGlobalScopes\(\)->)?where\(\s*\'is_main\'\s*,\s*true\s*\)->first\(\)->id/',
        "Company::withoutGlobalScopes()->value('id')",
        $src
    );
    $src = preg_replace(
        '/Branch::(?:withoutGlobalScopes\(\)->)?where\(\s*\'is_main\'\s*,\s*true\s*\)->first\(\)/',
        'Company::withoutGlobalScopes()->first()',
        $src
    );

    // Variables carrying that scope.
    $src = preg_replace('/\$mainBranch\b/', '$company', $src);
    $src = preg_replace('/\$branch_id\b/', '$companyId', $src);
    $src = preg_replace('/\$branchId\b/', '$companyId', $src);
    $src = preg_replace('/\$branch\b/', '$company', $src);

    // The column itself.
    $src = str_replace("'branch_id'", "'company_id'", $src);

    if (str_contains($src, 'Company::')
        && ! str_contains($src, 'use App\Models\Administration\Company;')) {
        $src = preg_replace(
            '/^(use .*?;)$/m',
            "use App\Models\Administration\Company;\n$1",
            $src,
            1
        );
    }

    if ($src !== $before) {
        file_put_contents($path, $src);
        $report[] = 'changed  ' . $rel;
    } else {
        $report[] = 'no-op    ' . $rel;
    }
}

echo implode("\n", $report) . "\n";
