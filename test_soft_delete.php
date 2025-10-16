<?php

require_once __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Administration\Category;

$category = Category::first();

if ($category) {
    echo 'Testing Category soft delete...' . PHP_EOL;
    echo 'Category: ' . $category->name . PHP_EOL;
    echo 'Can be deleted: ' . ($category->canBeDeleted() ? 'true' : 'false') . PHP_EOL;
    echo 'Dependencies: ' . json_encode($category->getDependencies()) . PHP_EOL;

    // Test soft delete
    $category->delete();
    echo 'Soft deleted successfully!' . PHP_EOL;
    echo 'Category deleted_at: ' . $category->deleted_at . PHP_EOL;
    echo 'Category is deleted: ' . ($category->isDeleted() ? 'true' : 'false') . PHP_EOL;

    // Test restore
    $category->restore();
    echo 'Restored successfully!' . PHP_EOL;
    echo 'Category deleted_at after restore: ' . $category->deleted_at . PHP_EOL;
} else {
    echo 'No categories found for testing' . PHP_EOL;
}
