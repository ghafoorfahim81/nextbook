<?php

namespace App\Services;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use Illuminate\Support\Collection;
use RuntimeException;

/**
 * Keeps an item's variants in step with what the form submitted.
 */
class ItemVariantService
{
    /**
     * Create, update and retire variants to match the payload.
     *
     * @param  array<int, array<string, mixed>>  $payload
     */
    public function sync(Item $item, array $payload): Collection
    {
        $payload = $this->normalise($payload);

        $existing = $item->variants()->get()->keyBy('id');
        $kept = [];

        foreach ($payload as $index => $row) {
            $variant = $this->resolve($item, $existing, $row);

            $variant->fill([
                'attributes' => (object) ($row['attributes'] ?? []),
                'name' => $row['name'] ?? null,
                'sku' => $row['sku'] ?? null,
                'barcode' => $row['barcode'] ?? null,
                'sale_price' => $row['sale_price'] ?? null,
                'purchase_price' => $row['purchase_price'] ?? null,
                'minimum_stock' => $row['minimum_stock'] ?? null,
                'maximum_stock' => $row['maximum_stock'] ?? null,
                'weight' => $row['weight'] ?? null,
                'sort_order' => $row['sort_order'] ?? $index,
                'is_default' => (bool) ($row['is_default'] ?? false),
                'is_active' => (bool) ($row['is_active'] ?? true),
            ]);

            $variant->item_id = $item->id;
            $variant->branch_id = $item->branch_id;
            $variant->save();

            $kept[] = $variant->id;
        }

        $this->retireMissing($existing, $kept);

        $variants = $item->variants()->get();

        $this->mirrorDefaultToItem($item, $variants);

        return $variants;
    }

    /**
     * Guarantee the item has its one default variant.
     *
     * For entry points that create an item without a variant grid (fast entry,
     * imports): seeds the single default row from the item's own identity and
     * price columns so variant_id is never null downstream.
     */
    public function ensureDefault(Item $item): ItemVariant
    {
        $existing = $item->variants()->orderBy('sort_order')->first();

        if ($existing !== null) {
            return $existing;
        }

        return $this->sync($item, [[
            'attributes' => [],
            'sku' => $item->sku,
            'barcode' => $item->barcode,
            'sale_price' => $item->sale_price,
            'purchase_price' => $item->purchase_price,
            'minimum_stock' => $item->minimum_stock,
            'maximum_stock' => $item->maximum_stock,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
        ]])->first();
    }

    /**
     * Keep the legacy item-level identity and price columns in step with the
     * default variant.
     *
     * SKU, barcode and price are variant-level now, but purchase/sale line
     * pricing and barcode scanning still read items.* until those modules are
     * moved over. Mirroring the default variant keeps them working; the columns
     * are dropped once nothing reads them.
     *
     * @param  Collection<int, ItemVariant>  $variants
     */
    private function mirrorDefaultToItem(Item $item, Collection $variants): void
    {
        $default = $variants->firstWhere('is_default', true) ?? $variants->first();

        if ($default === null) {
            return;
        }

        $item->forceFill([
            'sku' => $default->sku,
            'barcode' => $default->barcode,
            'purchase_price' => $default->purchase_price,
            'sale_price' => $default->sale_price,
            'minimum_stock' => $default->minimum_stock,
            'maximum_stock' => $default->maximum_stock,
        ])->save();
    }

    /**
     * Guarantee exactly one default, so price and barcode lookups always have
     * somewhere to fall back to.
     *
     * @param  array<int, array<string, mixed>>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function normalise(array $payload): array
    {
        $payload = array_values($payload);

        if (empty($payload)) {
            throw new RuntimeException('An item must have at least one variant.');
        }

        $defaultIndex = null;

        foreach ($payload as $index => $row) {
            if (! empty($row['is_default'])) {
                $defaultIndex = $index;
                break;
            }
        }

        $defaultIndex ??= 0;

        foreach ($payload as $index => $row) {
            $payload[$index]['is_default'] = $index === $defaultIndex;
        }

        return $payload;
    }

    /**
     * @param  Collection<string, ItemVariant>  $existing
     */
    private function resolve(Item $item, Collection $existing, array $row): ItemVariant
    {
        if (! empty($row['id']) && $existing->has($row['id'])) {
            return $existing->get($row['id']);
        }

        // Match on identity too: re-submitting the same attributes should
        // update the existing row rather than collide with its unique index.
        $key = ItemVariant::makeKey((array) ($row['attributes'] ?? []));

        $match = $existing->first(fn (ItemVariant $v) => $v->variant_key === $key);

        return $match ?? new ItemVariant();
    }

    /**
     * Variants dropped from the form are deactivated when they hold stock and
     * deleted when they never did — removing one that has been sold would
     * orphan its transaction lines.
     *
     * @param  Collection<string, ItemVariant>  $existing
     * @param  array<int, string>  $kept
     */
    private function retireMissing(Collection $existing, array $kept): void
    {
        $existing
            ->reject(fn (ItemVariant $variant) => in_array($variant->id, $kept, true))
            ->each(function (ItemVariant $variant) {
                if ($variant->canBeDeleted()) {
                    $variant->delete();

                    return;
                }

                $variant->update(['is_active' => false]);
            });
    }
}
