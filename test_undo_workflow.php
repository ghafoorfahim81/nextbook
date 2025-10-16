<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Administration\Category;

echo "=== Testing Undo/Delete Workflow ===\n\n";

// Test 1: Create a category without dependencies
echo "1. Creating a test category...\n";
$category = new Category();
$category->name = 'Test Category ' . time();
$category->remark = 'Test category for undo workflow';
$category->save();

echo "Created category: {$category->name} (ID: {$category->id})\n";

// Test 2: Check if it can be deleted (should be true since no dependencies)
echo "\n2. Checking if category can be deleted...\n";
$canDelete = $category->canBeDeleted();
$dependencies = $category->getDependencies();
echo "Can be deleted: " . ($canDelete ? 'YES' : 'NO') . "\n";
echo "Dependencies: " . (empty($dependencies) ? 'None' : json_encode($dependencies)) . "\n";

// Test 3: Soft delete the category
echo "\n3. Soft deleting the category...\n";
$category->delete();
echo "Category soft deleted successfully!\n";
echo "deleted_at: {$category->deleted_at}\n";
echo "isDeleted(): " . ($category->isDeleted() ? 'true' : 'false') . "\n";

// Test 4: Try to restore the category
echo "\n4. Restoring the category...\n";
$category->restore();
echo "Category restored successfully!\n";
echo "deleted_at after restore: {$category->deleted_at}\n";

// Test 5: Test dependency checking with existing data
echo "\n5. Testing dependency checking...\n";
// Since we don't want to create complex test data, let's just verify the dependency check works
$canDelete = $category->canBeDeleted();
$dependencies = $category->getDependencies();
echo "Can be deleted: " . ($canDelete ? 'YES' : 'NO') . "\n";
echo "Dependencies: " . (empty($dependencies) ? 'None' : json_encode($dependencies)) . "\n";

// Test 6: Soft delete the category (should work since no dependencies)
echo "\n6. Soft deleting the category (no dependencies)...\n";
$category->delete();
echo "Category soft deleted successfully!\n";
echo "deleted_at: {$category->deleted_at}\n";

// Test 7: Try to restore the category
echo "\n7. Restoring the category...\n";
$category->restore();
echo "Category restored successfully!\n";
echo "deleted_at after restore: {$category->deleted_at}\n";

// Test 8: Soft delete again for final cleanup
echo "\n8. Final soft delete for cleanup...\n";
$category->delete();
echo "Category soft deleted for cleanup\n";

// Clean up
echo "\n9. Cleaning up test records...\n";
$category->forceDelete();
echo "Test record permanently deleted\n";

echo "\n=== Test Complete ===\n";
