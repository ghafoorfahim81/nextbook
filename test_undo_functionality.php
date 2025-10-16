<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Administration\Category;

echo "=== Testing Undo Functionality ===\n\n";

// Test 1: Create a test category
echo "1. Creating a test category...\n";
$category = new Category();
$category->name = 'Test Undo Category ' . time();
$category->remark = 'Test category for undo functionality';
$category->save();

echo "Created category: {$category->name} (ID: {$category->id})\n";

// Test 2: Check if it can be deleted (should be true since no dependencies)
echo "\n2. Checking if category can be deleted...\n";
$canDelete = $category->canBeDeleted();
echo "Can be deleted: " . ($canDelete ? 'YES' : 'NO') . "\n";

// Test 3: Soft delete the category
echo "\n3. Soft deleting the category...\n";
$category->delete();
echo "Category soft deleted successfully!\n";
echo "deleted_at: {$category->deleted_at}\n";

// Test 4: Verify it's deleted
echo "\n4. Verifying category is deleted...\n";
echo "isDeleted(): " . ($category->isDeleted() ? 'true' : 'false') . "\n";

// Test 5: Restore the category
echo "\n5. Restoring the category...\n";
$category->restore();
echo "Category restored successfully!\n";
echo "deleted_at after restore: {$category->deleted_at}\n";

// Test 6: Verify it's restored
echo "\n6. Verifying category is restored...\n";
echo "isDeleted(): " . ($category->isDeleted() ? 'true' : 'false') . "\n";

// Clean up
echo "\n7. Cleaning up test record...\n";
$category->forceDelete();
echo "Test record permanently deleted\n";

echo "\n=== Undo Functionality Test Complete ===\n";
