<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Administration\Category;
use App\Models\Administration\Brand;
use App\Models\Administration\Branch;
use App\Models\Account\AccountType;
use App\Models\Account\Account;

echo "=== Testing Models with Soft Delete and Dependency Check ===\n\n";

// Test Category
echo "1. Testing Category...\n";
$category = Category::first();
if ($category) {
    echo "Category: {$category->name}\n";
    echo "Can be deleted: " . ($category->canBeDeleted() ? 'YES' : 'NO') . "\n";
    echo "Dependencies: " . (empty($category->getDependencies()) ? 'None' : json_encode($category->getDependencies())) . "\n";
    echo "Has SoftDeletes: " . (method_exists($category, 'trashed') ? 'YES' : 'NO') . "\n";
} else {
    echo "No categories found\n";
}

// Test Brand
echo "\n2. Testing Brand...\n";
$brand = Brand::first();
if ($brand) {
    echo "Brand: {$brand->name}\n";
    echo "Can be deleted: " . ($brand->canBeDeleted() ? 'YES' : 'NO') . "\n";
    echo "Dependencies: " . (empty($brand->getDependencies()) ? 'None' : json_encode($brand->getDependencies())) . "\n";
    echo "Has SoftDeletes: " . (method_exists($brand, 'trashed') ? 'YES' : 'NO') . "\n";
} else {
    echo "No brands found\n";
}

// Test Branch
echo "\n3. Testing Branch...\n";
$branch = Branch::first();
if ($branch) {
    echo "Branch: {$branch->name}\n";
    echo "Can be deleted: " . ($branch->canBeDeleted() ? 'YES' : 'NO') . "\n";
    echo "Dependencies: " . (empty($branch->getDependencies()) ? 'None' : json_encode($branch->getDependencies())) . "\n";
    echo "Has SoftDeletes: " . (method_exists($branch, 'trashed') ? 'YES' : 'NO') . "\n";
} else {
    echo "No branches found\n";
}

// Test AccountType
echo "\n4. Testing AccountType...\n";
$accountType = AccountType::first();
if ($accountType) {
    echo "AccountType: {$accountType->name}\n";
    echo "Can be deleted: " . ($accountType->canBeDeleted() ? 'YES' : 'NO') . "\n";
    echo "Dependencies: " . (empty($accountType->getDependencies()) ? 'None' : json_encode($accountType->getDependencies())) . "\n";
    echo "Has SoftDeletes: " . (method_exists($accountType, 'trashed') ? 'YES' : 'NO') . "\n";
} else {
    echo "No account types found\n";
}

// Test Account
echo "\n5. Testing Account...\n";
$account = Account::first();
if ($account) {
    echo "Account: {$account->name}\n";
    echo "Can be deleted: " . ($account->canBeDeleted() ? 'YES' : 'NO') . "\n";
    echo "Dependencies: " . (empty($account->getDependencies()) ? 'None' : json_encode($account->getDependencies())) . "\n";
    echo "Has SoftDeletes: " . (method_exists($account, 'trashed') ? 'YES' : 'NO') . "\n";
} else {
    echo "No accounts found\n";
}

echo "\n=== Test Complete ===\n";
