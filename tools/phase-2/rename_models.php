<?php

require_once __DIR__ . '/bootstrap.php';
/**
 * Swap the category A models from branch scope to company scope.
 *
 * Only the 30 model files are touched; every other model keeps branch_id.
 */

const MODELS = [
    'app\Models\Account\AccountType.php',
    'app\Models\Account\Account.php',
    'app\Models\Inventory\Item.php',
    'app\Models\Inventory\ItemVariant.php',
    'app\Models\Administration\Brand.php',
    'app\Models\Administration\Category.php',
    'app\Models\Administration\UnitMeasure.php',
    'app\Models\Administration\Quantity.php',
    'app\Models\Administration\Size.php',
    'app\Models\Administration\Currency.php',
    'app\Models\Ledger\Ledger.php',
    'app\Models\Administration\CustomerGroup.php',
    'app\Models\Administration\PaymentTerm.php',
    'app\Models\Inventory\DiscountRule.php',
    'app\Models\Expense\ExpenseCategory.php',
    'app\Models\JournalEntry\JournalClass.php',
    'app\Models\Administration\LandedCostCategory.php',
    'app\Models\Accounting\FinancialPeriod.php',
    'app\Models\Owner\Owner.php',
    'app\Models\Administration\Department.php',
    'app\Models\Administration\Designation.php',
    'app\Models\Hr\Shift.php',
    'app\Models\Hr\LeaveType.php',
    'app\Models\Hr\Holiday.php',
    'app\Models\Hr\HrSetting.php',
    'app\Models\Hr\SalaryComponent.php',
    'app\Models\Hr\SalaryStructure.php',
    'app\Models\Hr\SalaryStructureLine.php',
    'app\Models\Hr\TaxBracketSet.php',
    'app\Models\Hr\TaxBracket.php',
];

$dry = in_array('--dry', $argv, true);
$report = [];

foreach (MODELS as $rel) {
    $path = repo($rel);
    if (! is_file($path)) {
        $report[] = "MISSING  $rel";
        continue;
    }

    $src = file_get_contents($path);
    $before = $src;

    // Traits: scope and auto-fill.
    $src = str_replace(
        ['App\Traits\BranchSpecific', 'App\Traits\HasBranch'],
        ['App\Traits\CompanySpecific', 'App\Traits\HasCompany'],
        $src
    );
    $src = preg_replace('/\bBranchSpecific\b/', 'CompanySpecific', $src);
    $src = preg_replace('/\bHasBranch\b/', 'HasCompany', $src);

    // Column name in fillable, casts, searchable columns, scopes.
    $src = str_replace("'branch_id'", "'company_id'", $src);
    $src = str_replace('"branch_id"', '"company_id"', $src);

    // The belongsTo relation and its accessors.
    $src = preg_replace('/\bpublic function branch\(\)/', 'public function company()', $src);
    $src = preg_replace('/\bBranch::class\b/', 'Company::class', $src);
    $src = str_replace(
        'use App\Models\Administration\Branch;',
        'use App\Models\Administration\Company;',
        $src
    );
    $src = preg_replace('/->branch_id\b/', '->company_id', $src);

    if ($src !== $before) {
        $report[] = 'changed  ' . $rel;
        if (! $dry) file_put_contents($path, $src);
    } else {
        $report[] = 'no-op    ' . $rel;
    }
}

echo implode("\n", $report) . "\n";
