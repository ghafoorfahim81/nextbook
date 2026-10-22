<?php

namespace App\Traits;

use App\Models\Administration\UnitMeasure;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;

/**
 * Records, on a document line, how its item read when the line was written.
 *
 * The catalog is shared by every branch of a company, so a rename reaches every
 * document that ever named the item. A printed invoice has to keep saying what
 * it said on the day it was issued, so the line copies the item's name and
 * code, the variant's display name and the unit's name and symbol into its own
 * columns (2026_10_22_000001_add_item_snapshot_to_document_lines).
 *
 * The copy is taken when the line is created and again whenever its item,
 * variant or unit changes — editing a draft line to a different item has to
 * name the new one. Nothing else refreshes it.
 *
 * The snapshot*() readers are what resources use: the stored value, else the
 * live record for a line written before the columns existed.
 */
trait SnapshotsItem
{
    public static function bootSnapshotsItem(): void
    {
        static::saving(function ($line) {
            $unchanged = $line->exists
                && ! $line->isDirty(['item_id', 'variant_id', 'unit_measure_id'])
                && $line->item_name !== null;

            if (! $unchanged) {
                $line->takeItemSnapshot();
            }
        });
    }

    /**
     * Copy the current item, variant and unit onto the line.
     *
     * Read past the global scopes: a retired (soft-deleted) item is still what
     * the line sold, and the company scope is already guaranteed by the
     * composite foreign keys on the line.
     */
    public function takeItemSnapshot(): void
    {
        $item = $this->item_id
            ? Item::withoutGlobalScopes()->find($this->item_id)
            : null;

        $variant = $this->variant_id
            ? ItemVariant::withoutGlobalScopes()->find($this->variant_id)
            : null;

        $unit = $this->unit_measure_id
            ? UnitMeasure::withoutGlobalScopes()->find($this->unit_measure_id)
            : null;

        if ($variant && $item) {
            // displayName() falls back to the item's name; hand it the one
            // already loaded rather than letting it query again.
            $variant->setRelation('item', $item);
        }

        $this->item_name = $item?->name;
        $this->item_code = $item?->code;
        $this->variant_name = $variant?->displayName();
        $this->unit_name = $unit?->name;
        $this->unit_symbol = $unit?->symbol;
    }

    public function snapshotItemName(): ?string
    {
        return $this->item_name ?? $this->item?->name;
    }

    public function snapshotItemCode(): ?string
    {
        return $this->item_code ?? $this->item?->code;
    }

    public function snapshotVariantName(): ?string
    {
        return $this->variant_name ?? $this->variant?->displayName();
    }

    public function snapshotUnitName(): ?string
    {
        return $this->unit_name ?? $this->unitMeasure?->name;
    }

    public function snapshotUnitSymbol(): ?string
    {
        return $this->unit_symbol ?? $this->unitMeasure?->symbol;
    }
}
