<?php

require_once __DIR__ . '/bootstrap.php';
/**
 * Lines that scope a CATEGORY A model by branch_id.
 *
 * A line mentioning branch_id counts only when a category A model or table
 * appears within a few lines of it — the same statement, in practice. Files
 * that merely touch both kinds of model do not light up.
 */
$a = [
    'AccountType','Account','ItemVariant','Item','Brand','Category','UnitMeasure',
    'Quantity','Size','Currency','Ledger','CustomerGroup','PaymentTerm',
    'DiscountRule','ExpenseCategory','JournalClass','LandedCostCategory',
    'FinancialPeriod','Owner','Department','Designation','Shift','LeaveType',
    'Holiday','HrSetting','SalaryComponent','SalaryStructureLine','SalaryStructure',
    'TaxBracketSet','TaxBracket',
];
$tables = [
    'account_types','accounts','item_variants','items','brands','categories',
    'unit_measures','quantities','sizes','currencies','ledgers','customer_groups',
    'payment_terms','discount_rules','expense_categories','journal_classes',
    'landed_cost_categories','financial_periods','owners','departments',
    'designations','shifts','leave_types','holidays','hr_settings',
    'salary_components','salary_structure_lines','salary_structures',
    'tax_bracket_sets','tax_brackets',
];

$modelRe = '/\b(' . implode('|', $a) . ')::/';
$tableRe = '/[\'"](' . implode('|', $tables) . ')[\'"]/';

$roots = [repo('app'), repo('tests'), repo('database/seeders'), repo('database/factories')];
$total = 0;
$byFile = [];

foreach ($roots as $root) {
    if (! is_dir($root)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $lines = file($f->getPathname(), FILE_IGNORE_NEW_LINES);
        foreach ($lines as $i => $line) {
            if (! str_contains($line, 'branch_id')) continue;
            $window = implode("\n", array_slice($lines, max(0, $i - 4), 9));
            if (! preg_match($modelRe, $window) && ! preg_match($tableRe, $window)) continue;
            $byFile[rel($f->getPathname())][] = ($i + 1) . ': ' . trim($line);
            $total++;
        }
    }
}

ksort($byFile);
foreach ($byFile as $file => $hits) {
    echo "$file\n";
    foreach ($hits as $h) echo "    $h\n";
}
echo "\n$total lines in " . count($byFile) . " files\n";
