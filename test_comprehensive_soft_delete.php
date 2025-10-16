<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Administration\Category;
use App\Models\Administration\Brand;
use App\Models\Administration\Branch;
use App\Models\Account\AccountType;
use App\Models\Account\Account;
use App\Models\Inventory\Item;
use App\Models\Administration\Quantity;
use App\Models\Ledger\Ledger;
use App\Models\Administration\Company;

echo "=== Comprehensive Soft Delete and Dependency Check Test ===\n\n";

$testModels = [
    'Category' => Category::class,
    'Brand' => Brand::class,
    'Branch' => Branch::class,
    'AccountType' => AccountType::class,
    'Account' => Account::class,
    'Item' => Item::class,
    'Quantity' => Quantity::class,
    'Ledger' => Ledger::class,
    'Company' => Company::class,
];

foreach ($testModels as $name => $modelClass) {
    echo "Testing {$name}...\n";

    $model = $modelClass::first();
    if (!$model) {
        echo "❌ No {$name} records found\n\n";
        continue;
    }

    echo "✅ {$name}: {$model->name ?? $model->id}\n";

    // Check SoftDeletes
    $hasSoftDeletes = method_exists($model, 'trashed');
    echo "SoftDeletes: " . ($hasSoftDeletes ? '✅' : '❌') . "\n";

    // Check HasDependencyCheck
    $hasDependencyCheck = method_exists($model, 'getRelationships');
    echo "HasDependencyCheck: " . ($hasDependencyCheck ? '✅' : '❌') . "\n";

    if ($hasDependencyCheck) {
        $canDelete = $model->canBeDeleted();
        $dependencies = $model->getDependencies();
        echo "Can be deleted: " . ($canDelete ? '✅ YES' : '❌ NO') . "\n";
        echo "Dependencies: " . (empty($dependencies) ? 'None' : json_encode($dependencies)) . "\n";
    }

    echo "\n";
}

echo "=== Test Complete ===\n";
