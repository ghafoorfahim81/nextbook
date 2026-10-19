<?php

require_once __DIR__ . '/bootstrap.php';

/**
 * `alias.branch_id` where the alias is bound to a table without branch_id.
 *
 * report_aliases.php does this for ReportService alone. The same query shape
 * lives in DashboardService and NotificationService, which no test reached
 * until the integration suite did, so this runs over the whole of app/.
 *
 * Aliases are read from each file's from/join clauses. An alias that is bound
 * to a company table in one query and a document table in another (`a` for
 * accounts here, attendances there) is reported as ambiguous: look at the
 * query, the file-wide map cannot tell.
 */
const NO_BRANCH_TABLES = [
    'account_types', 'accounts', 'brands', 'categories', 'currencies',
    'customer_groups', 'departments', 'designations', 'discount_rules',
    'expense_categories', 'financial_periods', 'holidays', 'hr_settings',
    'invoice_formats', 'item_variants', 'items', 'journal_classes',
    'journal_entry_lines', 'landed_cost_categories', 'landed_cost_purchases',
    'leave_types', 'ledgers', 'owners', 'payment_terms', 'quantities',
    'salary_components', 'salary_structure_lines', 'salary_structures',
    'shifts', 'sizes', 'tax_bracket_sets', 'tax_brackets', 'transaction_lines',
    'unit_measures',
    // Category C, step 7: lines and child records take the parent's branch.
    'sale_items', 'sale_order_items', 'sale_quotation_items', 'sale_return_items',
    'purchase_items', 'purchase_order_items', 'purchase_quotation_items',
    'purchase_return_items', 'stock_adjustment_items', 'item_transfer_items',
    'expense_details', 'payroll_lines', 'payroll_line_components',
    'salary_payment_lines', 'landed_cost_items', 'landed_cost_category_allocations',
    'employee_documents', 'employee_contracts', 'interview_panelists', 'attachments',
];

$patterns = [
    "/->(?:from|join|leftJoin|rightJoin|joinSub|fromSub)\(\s*'([a-z_]+) as ([a-z_0-9]+)'/",
    "/DB::table\(\s*'([a-z_]+) as ([a-z_0-9]+)'/",
    '/\b(?:FROM|JOIN)\s+([a-z_]+)\s+(?:AS\s+)?([a-z_0-9]+)\b/i',
];

$sure = [];
$ambiguous = [];

foreach (phpFiles(repo('app')) as $file) {
    $src = file_get_contents($file);
    $map = [];

    foreach ($patterns as $re) {
        preg_match_all($re, $src, $m, PREG_SET_ORDER);
        foreach ($m as [, $table, $alias]) {
            $alias = strtolower($alias);
            if (in_array($alias, ['on', 'as', 'where', 'left', 'inner', 'join'], true)) {
                continue;
            }
            $map[$alias][strtolower($table)] = true;
        }
    }

    preg_match_all('/\b([a-z_0-9]+)\.branch_id\b/', $src, $m, PREG_OFFSET_CAPTURE);
    foreach ($m[1] as [$alias, $offset]) {
        $tables = array_keys($map[$alias] ?? []);
        $company = array_intersect($tables, NO_BRANCH_TABLES);
        if ($company === []) {
            continue;
        }

        $line = substr_count($src, "\n", 0, $offset) + 1;
        $entry = sprintf('%s:%d  %s -> %s', rel($file), $line, $alias, implode(', ', $tables));

        if (count($company) === count($tables)) {
            $sure[] = $entry;
        } else {
            $ambiguous[] = $entry;
        }
    }
}

echo 'COMPANY TABLE FILTERED BY BRANCH ('.count($sure)."):\n";
foreach ($sure as $e) {
    echo "  $e\n";
}

echo "\nAMBIGUOUS ALIAS — CHECK BY HAND (".count($ambiguous)."):\n";
foreach ($ambiguous as $e) {
    echo "  $e\n";
}
