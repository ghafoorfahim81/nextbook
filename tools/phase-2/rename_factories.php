<?php

require_once __DIR__ . '/bootstrap.php';
/**
 * Swap the category A factories to company scope.
 *
 * Found by reading each factory's `protected $model` rather than guessing from
 * the filename, so a factory that names an unexpected model is skipped instead
 * of silently mangled.
 */
const MODEL_CLASSES = [
    'App\Models\Account\AccountType','App\Models\Account\Account',
    'App\Models\Inventory\Item','App\Models\Inventory\ItemVariant',
    'App\Models\Administration\Brand','App\Models\Administration\Category',
    'App\Models\Administration\UnitMeasure','App\Models\Administration\Quantity',
    'App\Models\Administration\Size','App\Models\Administration\Currency',
    'App\Models\Ledger\Ledger','App\Models\Administration\CustomerGroup',
    'App\Models\Administration\PaymentTerm','App\Models\Inventory\DiscountRule',
    'App\Models\Expense\ExpenseCategory','App\Models\JournalEntry\JournalClass',
    'App\Models\Administration\LandedCostCategory','App\Models\Accounting\FinancialPeriod',
    'App\Models\Owner\Owner','App\Models\Administration\Department',
    'App\Models\Administration\Designation','App\Models\Hr\Shift',
    'App\Models\Hr\LeaveType','App\Models\Hr\Holiday','App\Models\Hr\HrSetting',
    'App\Models\Hr\SalaryComponent','App\Models\Hr\SalaryStructure',
    'App\Models\Hr\SalaryStructureLine','App\Models\Hr\TaxBracketSet',
    'App\Models\Hr\TaxBracket',
];

$short = array_map(fn ($c) => substr($c, strrpos($c, '\\') + 1), MODEL_CLASSES);

$it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(repo('database/factories')));
$changed = [];

foreach ($it as $f) {
    if ($f->getExtension() !== 'php') continue;
    $path = $f->getPathname();
    $src = file_get_contents($path);

    if (! preg_match('/protected \$model\s*=\s*([A-Za-z]+)::class/', $src, $m)) continue;
    if (! in_array($m[1], $short, true)) continue;
    if (! str_contains($src, 'branch_id')) continue;

    $before = $src;

    $src = str_replace(
        "'branch_id' => Branch::factory()",
        "'company_id' => Company::factory()",
        $src
    );
    $src = str_replace("'branch_id'", "'company_id'", $src);
    $src = preg_replace('/\$branch_id\b/', '$company_id', $src);

    if (str_contains($src, 'Company::factory()')
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
        $changed[] = rel($path) . '  (' . $m[1] . ')';
    }
}

echo implode("\n", $changed) . "\n\n" . count($changed) . " factories changed\n";
