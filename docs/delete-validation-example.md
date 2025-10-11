# Delete Validation Example

This document demonstrates how the delete validation system works in the application.

## How It Works

The system uses a `HasDependencyCheck` trait that can be added to any model to prevent deletion when dependencies exist.

### Example: UnitMeasure Model

When trying to delete a UnitMeasure that is used in items or stock records:

```php
// In UnitMeasureController
public function destroy(Request $request, UnitMeasure $unitMeasure)
{
    // Check for dependencies before deletion
    if (!$unitMeasure->canBeDeleted()) {
        $message = $unitMeasure->getDependencyMessage();
        return redirect()->route('unit-measures.index')->with('error', $message);
    }

    $unitMeasure->delete();
    return redirect()->route('unit-measures.index')->with('success', 'Unit measure deleted successfully.');
}
```

### Error Messages

The system will show user-friendly error messages like:
- "Cannot delete this record because it's used in 5 items and 12 stock records. Please delete those records first."
- "Cannot delete this record because it's used in 3 subcategories. Please delete those records first."

### Frontend Integration

The frontend delete composable automatically handles dependency errors and shows appropriate toast notifications without the "Try Again" button for dependency errors.

## Supported Models

Currently implemented for:
- UnitMeasure (checks items and stocks)
- Category (checks items and subcategories)  
- Department (checks subdepartments)

## Adding to New Models

To add dependency checking to a new model:

1. Add the `HasDependencyCheck` trait to your model
2. Implement the `getRelationships()` method to define which relationships to check
3. Add the actual relationship methods
4. Update the controller's `destroy` method to check dependencies

Example:
```php
use App\Traits\HasDependencyCheck;

class MyModel extends Model
{
    use HasDependencyCheck;
    
    protected function getRelationships(): array
    {
        return [
            'related_items' => [
                'model' => 'related items',
                'message' => 'This record is used in related items'
            ]
        ];
    }
    
    public function related_items()
    {
        return $this->hasMany(RelatedItem::class);
    }
}
```
