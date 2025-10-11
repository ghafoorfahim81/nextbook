<?php

require_once 'vendor/autoload.php';

// Bootstrap Laravel
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;

echo "Testing UnitMeasure dependency checking...\n\n";

// Get a UnitMeasure that might have dependencies
$unitMeasure = UnitMeasure::first();

if (!$unitMeasure) {
    echo "No UnitMeasure found in database.\n";
    exit;
}

echo "Testing UnitMeasure ID: {$unitMeasure->id}\n";
echo "UnitMeasure Name: {$unitMeasure->name}\n\n";

// Test the relationships directly
echo "Testing relationships:\n";
echo "Items count: " . $unitMeasure->items()->count() . "\n";
echo "Stocks count: " . $unitMeasure->stocks()->count() . "\n\n";

// Test the dependency checking
echo "Testing dependency checking:\n";
echo "Can be deleted: " . ($unitMeasure->canBeDeleted() ? 'YES' : 'NO') . "\n";
echo "Dependencies: " . json_encode($unitMeasure->getDependencies(), JSON_PRETTY_PRINT) . "\n";
echo "Dependency message: " . ($unitMeasure->getDependencyMessage() ?? 'None') . "\n";
