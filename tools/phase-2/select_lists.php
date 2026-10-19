<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * Explicit column lists that still ask a company-scoped table for branch_id.
 *
 * `select([... 'branch_id'])` and `$fillable`-style arrays do not look like
 * queries, so the other scanners miss them — but the column is gone, and
 * Postgres rejects the whole statement.
 *
 * A list is attributed to the nearest model or table named above it.
 */
const COMPANY_MODELS = [
    'AccountType','Account','ItemVariant','Item','Brand','Category','UnitMeasure',
    'Quantity','Size','Currency','Ledger','CustomerGroup','PaymentTerm',
    'DiscountRule','ExpenseCategory','JournalClass','LandedCostCategory',
    'FinancialPeriod','Owner','Department','Designation','Shift','LeaveType',
    'Holiday','HrSetting','SalaryComponent','SalaryStructure','TaxBracketSet','TaxBracket',
];
const COMPANY_TABLES = [
    'account_types','accounts','item_variants','items','brands','categories',
    'unit_measures','quantities','sizes','currencies','ledgers','customer_groups',
    'payment_terms','discount_rules','expense_categories','journal_classes',
    'landed_cost_categories','financial_periods','owners','departments',
    'designations','shifts','leave_types','holidays','hr_settings',
    'salary_components','salary_structures','tax_bracket_sets','tax_brackets',
];

$fix = in_array('--fix', $argv, true);
$hits = [];

foreach (['app', 'tests', 'database/seeders'] as $root) {
    foreach (phpFiles(repo($root)) as $path) {
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $dirty = false;

        foreach ($lines as $i => $line) {
            // A bare 'branch_id', entry in an array literal.
            if (! preg_match("/^\s*'branch_id',?\s*$/", $line)) {
                continue;
            }

            $owner = null;
            for ($j = $i; $j >= max(0, $i - 25); $j--) {
                foreach (COMPANY_MODELS as $m) {
                    if (preg_match('/\b' . $m . '::/', $lines[$j])) { $owner = $m; break 2; }
                }
                foreach (COMPANY_TABLES as $t) {
                    if (preg_match("/['\"]" . $t . "['\"]/", $lines[$j])) { $owner = $t; break 2; }
                }
                // A different model interrupts the search.
                if (preg_match('/\b(Sale|Purchase|Transaction|Payment|Receipt|Employee|Warehouse|StockMovement|Expense)::/', $lines[$j])) {
                    break;
                }
            }

            if ($owner === null) {
                continue;
            }

            $hits[] = rel($path) . ':' . ($i + 1) . "  [$owner]";

            if ($fix) {
                $lines[$i] = str_replace("'branch_id'", "'company_id'", $line);
                $dirty = true;
            }
        }

        if ($dirty) {
            file_put_contents($path, implode("\n", $lines) . "\n");
        }
    }
}

echo ($fix ? 'rewritten' : 'found') . ' ' . count($hits) . ":\n  " . implode("\n  ", $hits) . "\n";
