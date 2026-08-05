<?php

namespace App\Console\Commands;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Give every item its variants, so variant_id can be relied on everywhere.
 *
 * Items that already track colour or size get one variant per distinct
 * (colour, size) combination actually present in stock_balances — that is
 * where the real variant data lives today. Everything else gets a single
 * default variant carrying the item's own SKU, barcode and price.
 *
 * Idempotent: re-running only creates what is missing.
 */
class BackfillItemVariants extends Command
{
    protected $signature = 'items:backfill-variants
                            {--dry-run : Report what would be created without writing}
                            {--item= : Restrict to a single item id}';

    protected $description = 'Create the default (and colour/size) variants for existing items';

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $query = Item::query()->withoutGlobalScopes();

        if ($itemId = $this->option('item')) {
            $query->whereKey($itemId);
        }

        $total = $query->count();

        if ($total === 0) {
            $this->info('No items to process.');

            return self::SUCCESS;
        }

        $this->info(($dryRun ? '[dry run] ' : '') . "Processing {$total} item(s)...");

        $created = 0;
        $skipped = 0;
        $bar = $this->output->createProgressBar($total);

        $query->chunkById(200, function ($items) use (&$created, &$skipped, $dryRun, $bar) {
            foreach ($items as $item) {
                $combinations = $this->combinationsFor($item);

                foreach ($combinations as $index => $attributes) {
                    $key = ItemVariant::makeKey($attributes);

                    $exists = ItemVariant::query()
                        ->withoutGlobalScopes()
                        ->where('item_id', $item->id)
                        ->where('variant_key', $key)
                        ->whereNull('deleted_at')
                        ->exists();

                    if ($exists) {
                        $skipped++;
                        continue;
                    }

                    if (! $dryRun) {
                        $this->createVariant($item, $attributes, $index);
                    }

                    $created++;
                }

                $bar->advance();
            }
        });

        $bar->finish();
        $this->newLine(2);

        $this->info(($dryRun ? 'Would create' : 'Created') . ": {$created} variant(s)");
        $this->line("Already present: {$skipped}");

        if ($dryRun) {
            $this->comment('Nothing was written. Re-run without --dry-run to apply.');
        }

        return self::SUCCESS;
    }

    /**
     * The variant attribute sets this item needs.
     *
     * Reads colour/size straight off stock_balances rather than off the item's
     * `colors` json, because the balances record what was actually received —
     * the json is only what someone typed on the form.
     */
    private function combinationsFor(Item $item): array
    {
        if (! $item->is_color_tracked && ! $item->is_size_tracked) {
            return [[]];
        }

        $rows = DB::table('stock_balances')
            ->select('color', 'size_id')
            ->where('item_id', $item->id)
            ->whereNull('deleted_at')
            ->groupBy('color', 'size_id')
            ->get();

        $combinations = [];

        foreach ($rows as $row) {
            $attributes = [];

            if ($item->is_color_tracked && filled($row->color)) {
                $attributes['color'] = $row->color;
            }

            if ($item->is_size_tracked && filled($row->size_id)) {
                $attributes['size'] = $this->sizeName($row->size_id);
            }

            if (! empty($attributes)) {
                $combinations[] = $attributes;
            }
        }

        // An item flagged as tracked but with no stock yet still needs
        // somewhere to hang its price and barcode.
        if (empty($combinations)) {
            return [[]];
        }

        return $combinations;
    }

    private function sizeName(string $sizeId): string
    {
        static $cache = [];

        return $cache[$sizeId] ??= (string) DB::table('sizes')
            ->where('id', $sizeId)
            ->value('name');
    }

    private function createVariant(Item $item, array $attributes, int $index): void
    {
        $isDefault = $index === 0;

        ItemVariant::withoutGlobalScopes()->create([
            'item_id' => $item->id,
            'attributes' => (object) $attributes,
            'name' => empty($attributes) ? null : implode(' / ', array_values($attributes)),
            // Only the default variant inherits the item's identifiers —
            // the unique indexes would reject a second copy.
            'sku' => $isDefault ? $item->sku : null,
            'barcode' => $isDefault ? $item->barcode : null,
            'sale_price' => $item->sale_price,
            'purchase_price' => $item->purchase_price,
            'avg_cost' => $item->avg_cost ?? 0,
            'minimum_stock' => $item->minimum_stock,
            'maximum_stock' => $item->maximum_stock,
            'sort_order' => $index,
            'is_default' => $isDefault,
            'is_active' => true,
            'branch_id' => $item->branch_id,
            'created_by' => $item->created_by,
        ]);
    }
}
