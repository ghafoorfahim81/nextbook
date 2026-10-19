<?php

require_once __DIR__ . '/bootstrap.php';
/**
 * Rewrite branch scoping to company scoping, but only where the statement
 * actually belongs to a category A model.
 *
 * The same line — `'branch_id' => $this->ctx['branch']->id` — is correct for a
 * Sale and wrong for an Item, so the line alone cannot decide. Each branch_id
 * line is attributed to the nearest model anchor above it (Model::factory(),
 * Model::create(, Model::query(), new Model, DB::table('t')) and is only
 * touched when that anchor is one of the 30.
 *
 * Anything it cannot convert safely is reported rather than guessed at.
 */
$A = [
    'AccountType','Account','ItemVariant','Item','Brand','Category','UnitMeasure',
    'Quantity','Size','Currency','Ledger','CustomerGroup','PaymentTerm',
    'DiscountRule','ExpenseCategory','JournalClass','LandedCostCategory',
    'FinancialPeriod','Owner','Department','Designation','Shift','LeaveType',
    'Holiday','HrSetting','SalaryComponent','SalaryStructureLine','SalaryStructure',
    'TaxBracketSet','TaxBracket',
];
$ATables = [
    'account_types','accounts','item_variants','items','brands','categories',
    'unit_measures','quantities','sizes','currencies','ledgers','customer_groups',
    'payment_terms','discount_rules','expense_categories','journal_classes',
    'landed_cost_categories','financial_periods','owners','departments',
    'designations','shifts','leave_types','holidays','hr_settings',
    'salary_components','salary_structure_lines','salary_structures',
    'tax_bracket_sets','tax_brackets',
];
// Models that keep branch_id and must never be rewritten even if nearby.
$B = ['Warehouse','Sale','Purchase','Transaction','StockMovement','StockBalance',
      'StockLot','StockPiece','Employee','Payment','Receipt','Expense','User','Branch'];

$anchorRe = '/\b([A-Z][A-Za-z]*)::(?:factory|query|create|withoutGlobalScopes|where|firstOrCreate|updateOrCreate)\b'
          . '|\bnew\s+([A-Z][A-Za-z]*)\s*\('
          . '|DB::table\(\s*[\'"]([a-z_]+)[\'"]/';

$roots = [repo('app'), repo('tests'), repo('database/seeders'), repo('database/factories')];
$converted = 0; $skipped = []; $filesTouched = [];

foreach ($roots as $root) {
    if (! is_dir($root)) continue;
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
    foreach ($it as $f) {
        if ($f->getExtension() !== 'php') continue;
        $path = $f->getPathname();
        $lines = file($path, FILE_IGNORE_NEW_LINES);
        $dirty = false;

        foreach ($lines as $i => $line) {
            if (! str_contains($line, 'branch_id')) continue;

            // Nearest anchor above (and on) this line.
            $anchor = null;
            for ($j = $i; $j >= max(0, $i - 15); $j--) {
                if (preg_match_all($anchorRe, $lines[$j], $m, PREG_SET_ORDER)) {
                    $last = end($m);
                    $anchor = $last[1] ?: ($last[2] ?? '') ?: ($last[3] ?? '');
                    break;
                }
            }
            if ($anchor === null || $anchor === '') continue;
            if (in_array($anchor, $B, true)) continue;
            if (! in_array($anchor, $A, true) && ! in_array($anchor, $ATables, true)) continue;

            $new = $line;
            $new = str_replace("'branch_id'", "'company_id'", $new);
            $new = str_replace('"branch_id"', '"company_id"', $new);
            $new = str_replace("ctx['branch']", "ctx['company']", $new);
            $new = str_replace('ctx["branch"]', 'ctx["company"]', $new);
            $new = preg_replace('/\$branch->id\b/', '$company->id', $new);
            $new = preg_replace('/\$branchId\b/', '$companyId', $new);
            $new = preg_replace('/\$branch_id\b/', '$company_id', $new);

            if ($new === $line) continue;

            if (str_contains($new, 'branch')) {
                $skipped[] = rel($path) . ':' . ($i + 1)
                    . "  [$anchor]  " . trim($line);
                continue;
            }

            $lines[$i] = $new;
            $dirty = true;
            $converted++;
        }

        if ($dirty) {
            file_put_contents($path, implode("\n", $lines) . "\n");
            $filesTouched[] = rel($path);
        }
    }
}

echo "converted $converted lines in " . count($filesTouched) . " files\n\n";
if ($skipped) {
    echo "NEEDS A LOOK (" . count($skipped) . "):\n";
    foreach (array_slice($skipped, 0, 40) as $s) echo "  $s\n";
    if (count($skipped) > 40) echo '  ... +' . (count($skipped) - 40) . " more\n";
}
