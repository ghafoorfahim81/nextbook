<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Hunt the two failure modes the automated rewrites keep producing.
 *
 * PHP reports neither at compile time, so `php -l` is silent and the code ships
 * looking fine:
 *
 *  1. $companyId used in a scope that never defines it — a null comparison,
 *     which matches nothing.
 *  2. A company column compared against a branch value, or the reverse — valid
 *     SQL that quietly returns the wrong rows.
 *
 * Both are the same mistake: the column moved up a level and the value did not.
 */

$undefined = [];
$mixed = [];

foreach (['app', 'database/seeders', 'database/factories', 'tests'] as $root) {
    foreach (phpFiles(repo($root)) as $path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            continue;
        }

        $fnName = '?';
        $fnLine = 0;
        $defined = false;

        foreach ($lines as $i => $line) {
            // A function signature resets the scope.
            if (preg_match('/^\s*(?:public|protected|private|static|\s)*function\s+(\w+)?\s*\(/', $line)) {
                $sig = $line;
                for ($j = $i + 1; $j <= min($i + 6, count($lines) - 1) && ! str_contains($sig, '{'); $j++) {
                    $sig .= $lines[$j];
                }
                preg_match('/function\s+(\w+)/', $line, $nm);
                $fnName = $nm[1] ?? 'closure';
                $fnLine = $i + 1;
                $defined = str_contains($sig, '$companyId');
                continue;
            }

            if (preg_match('/\$companyId\s*=[^=]/', $line) || preg_match('/use\s*\([^)]*\$companyId/', $line)) {
                $defined = true;
            }

            // `$companyIds` (the plural, from CompanySpecific) and property
            // declarations are not uses of the scalar.
            $usesScalar = preg_match('/\$companyId\b/', $line)
                && ! preg_match('/(?:private|protected|public)\s+[\w?|]+\s+\$companyId\s*;/', $line);

            if ($usesScalar && ! $defined) {
                $undefined[] = sprintf('%s:%d  (%s, line %d)  %s', rel($path), $i + 1, $fnName, $fnLine, trim($line));
            }

            // Column at one level, value from the other.
            $hasCompanyCol = preg_match('/[\'"][\w.]*company_id[\'"]/', $line);
            $hasBranchCol = preg_match('/[\'"][\w.]*branch_id[\'"]/', $line);
            $hasCompanyVal = preg_match('/\$companyId\b|\[\'company_id\'\]|->company_id\b/', $line);
            $hasBranchVal = preg_match('/\$branchId\b|\[\'branch_id\'\]|->branch_id\b/', $line);

            // Deriving one from the other legitimately mentions both:
            //   $companyId = ...branches...->value('company_id');
            $derives = preg_match('/\$companyId\s*=.*(branches|Branch::)/', $line)
                || preg_match('/\$branchId\s*=.*\bcompany\b/', $line)
                // 'company_id' => <branch lookup> is the same derivation,
                // written inline into a row.
                || preg_match("/'company_id'\s*=>.*Branch::.*value\('company_id'\)/", $line)
                // companyOf($branchId) is the memoised form of that lookup in
                // DashboardService and NotificationService.
                || preg_match('/companyOf\(\$branchId\)/', $line);

            if (! $derives
                && (($hasCompanyCol && $hasBranchVal && ! $hasBranchCol)
                    || ($hasBranchCol && $hasCompanyVal && ! $hasCompanyCol))) {
                $mixed[] = sprintf('%s:%d  %s', rel($path), $i + 1, trim($line));
            }
        }
    }
}

echo 'UNDEFINED $companyId (' . count($undefined) . "):\n";
foreach (array_slice($undefined, 0, 30) as $u) {
    echo "  $u\n";
}

echo "\nCOLUMN/VALUE MISMATCH (" . count($mixed) . "):\n";
foreach (array_slice($mixed, 0, 30) as $m) {
    echo "  $m\n";
}
