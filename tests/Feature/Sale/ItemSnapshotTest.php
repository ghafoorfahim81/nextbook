<?php

namespace Tests\Feature\Sale;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Models\Purchase\Purchase;
use App\Models\Purchase\PurchaseItem;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleItem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * A document line keeps saying what its item was called when it was written.
 *
 * The catalog is shared by every branch of a company, so renaming an item used
 * to rename it on every invoice it ever appeared on. SnapshotsItem copies the
 * item, variant and unit onto the line instead.
 */
class ItemSnapshotTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();
    }

    /**
     * @return array<string, array{class-string<Model>, class-string<Model>, string}>
     */
    public static function lineModels(): array
    {
        return [
            'sale line' => [SaleItem::class, Sale::class, 'sale_id'],
            'purchase line' => [PurchaseItem::class, Purchase::class, 'purchase_id'],
        ];
    }

    /**
     * A line on a document in the context's own branch. The document
     * factories otherwise build a branch of their own, in a company the
     * connection is not acting for.
     */
    private function makeLine(string $model, string $parent, string $foreignKey): Model
    {
        $document = $parent::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        return $model::factory()->create([$foreignKey => $document->id]);
    }

    #[DataProvider('lineModels')]
    public function test_a_line_records_its_item_and_unit_when_written(string $model, string $parent, string $foreignKey): void
    {
        $line = $this->makeLine($model, $parent, $foreignKey);

        $this->assertSame($line->item->name, $line->item_name);
        $this->assertSame($line->item->code, $line->item_code);
        $this->assertSame($line->unitMeasure->name, $line->unit_name);
        $this->assertSame($line->unitMeasure->symbol, $line->unit_symbol);
        $this->assertSame($line->variant?->displayName(), $line->variant_name);
    }

    #[DataProvider('lineModels')]
    public function test_renaming_the_item_or_unit_does_not_rewrite_the_line(string $model, string $parent, string $foreignKey): void
    {
        $line = $this->makeLine($model, $parent, $foreignKey);
        $originalItem = $line->item_name;
        $originalCode = $line->item_code;
        $originalUnit = $line->unit_name;

        $line->item->update(['name' => 'Renamed item', 'code' => 'RENAMED-1']);
        $line->unitMeasure->update(['name' => 'Renamed unit']);

        // Saving the line for an unrelated reason must not pick the rename up.
        $line = $line->fresh();
        $line->update(['quantity' => (float) $line->quantity + 1]);
        $line = $line->fresh();

        $this->assertSame($originalItem, $line->snapshotItemName());
        $this->assertSame($originalCode, $line->snapshotItemCode());
        $this->assertSame($originalUnit, $line->snapshotUnitName());
    }

    #[DataProvider('lineModels')]
    public function test_pointing_a_line_at_another_item_takes_a_new_snapshot(string $model, string $parent, string $foreignKey): void
    {
        $line = $this->makeLine($model, $parent, $foreignKey);
        $other = Item::factory()->create(['company_id' => $line->company_id]);

        $line->update(['item_id' => $other->id, 'variant_id' => null]);

        $line = $line->fresh();
        $this->assertSame($other->name, $line->item_name);
        $this->assertSame($other->code, $line->item_code);
    }

    /**
     * The migration fills lines that existed before it, including a variant
     * named only by its attributes — the SQL has to reach the same answer as
     * ItemVariant::displayName().
     */
    #[DataProvider('lineModels')]
    public function test_the_migration_fills_lines_written_before_it(string $model, string $parent, string $foreignKey): void
    {
        $line = $this->makeLine($model, $parent, $foreignKey);

        $variant = ItemVariant::create([
            'item_id' => $line->item_id,
            'variant_key' => 'color:red|size:l',
            'attributes' => ['color' => 'Red', 'size' => 'L'],
            'is_default' => false,
        ]);
        DB::table($line->getTable())->where('id', $line->id)->update(['variant_id' => $variant->id]);

        $migration = require database_path('migrations/2026_10_22_000001_add_item_snapshot_to_document_lines.php');
        $this->asSchemaOwner(function () use ($migration): void {
            $migration->down();
            $migration->up();
        });

        $line = $line->fresh();
        $this->assertSame($line->item->name, $line->item_name);
        $this->assertSame($line->item->code, $line->item_code);
        $this->assertSame($line->unitMeasure->name, $line->unit_name);
        $this->assertSame($line->unitMeasure->symbol, $line->unit_symbol);
        // Against the stored variant, not the literal 'Red / L': jsonb keeps its
        // keys in its own order, and the live display reads them back the same way.
        $this->assertSame($variant->fresh()->displayName(), $line->variant_name);
        $this->assertStringContainsString(' / ', $line->variant_name);
    }

    /**
     * A line written before the columns existed has no snapshot; the readers
     * fall back to the live record rather than showing nothing.
     */
    #[DataProvider('lineModels')]
    public function test_a_line_without_a_snapshot_falls_back_to_the_live_item(string $model, string $parent, string $foreignKey): void
    {
        $line = $this->makeLine($model, $parent, $foreignKey);

        DB::table($line->getTable())->where('id', $line->id)->update([
            'item_name' => null,
            'item_code' => null,
            'variant_name' => null,
            'unit_name' => null,
            'unit_symbol' => null,
        ]);

        $line = $line->fresh();

        $this->assertSame($line->item->name, $line->snapshotItemName());
        $this->assertSame($line->unitMeasure->name, $line->snapshotUnitName());
    }
}
