<?php

namespace App\Console\Commands;

use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use App\Services\StockService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Re-derives every item and variant average from the movements still on record.
 *
 * A repair, not part of any workflow. Averages are maintained as a running
 * figure while documents are posted, and a running figure cannot recover from
 * a reversal that undoes an ISSUE: the receipts booked after that issue blended
 * against a shelf the issue had already emptied. Reversals fix themselves from
 * now on (TransactionService::voidStockMovementsFor), but rows that drifted
 * before that need this once.
 *
 * Safe to run repeatedly: it reads the movement history and writes the figure
 * that history implies, so a second run changes nothing.
 */
class RecalculateStockAverages extends Command
{
    protected $signature = 'stock:recalculate-averages
        {--item= : Limit to one item id}
        {--dry-run : Report what would change, then roll it back}';

    protected $description = 'Re-derive item and variant average costs from stock movements';

    public function handle(StockService $stockService): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $items = Item::query()
            ->withoutGlobalScopes()
            ->when($this->option('item'), fn ($query) => $query->whereKey($this->option('item')))
            ->get(['id', 'name', 'avg_cost', 'unit_measure_id']);

        if ($items->isEmpty()) {
            $this->warn('No items matched.');

            return self::SUCCESS;
        }

        // A dry run has to do the work to know what the work would change, so
        // it runs for real inside a transaction and rolls it back.
        DB::beginTransaction();

        try {
            $changed = $this->replayAll($stockService, $items);
        } catch (\Throwable $e) {
            DB::rollBack();

            throw $e;
        }

        $dryRun ? DB::rollBack() : DB::commit();

        if ($changed === []) {
            $this->info('Every average already matched its movement history.');

            return self::SUCCESS;
        }

        $this->table(['Item', 'Was', 'Now', 'Variants changed'], $changed);
        $this->info(($dryRun ? 'Would re-derive ' : 'Re-derived ') . count($changed) . ' item(s).');

        if ($dryRun) {
            $this->warn('Dry run: nothing was kept.');
        }

        return self::SUCCESS;
    }

    /** @return list<array{0: string, 1: string, 2: string, 3: int}> */
    private function replayAll(StockService $stockService, $items): array
    {
        $changed = [];

        foreach ($items as $item) {
            $before = (float) $item->avg_cost;
            $variantsBefore = $this->variantAverages($item->id);

            $stockService->recalculateItemAverage($item->id);

            foreach ($variantsBefore->keys() as $variantId) {
                $stockService->recalculateVariantAverage($variantId);
            }

            $after = (float) $item->fresh()->avg_cost;

            $movedVariants = $this->variantAverages($item->id)
                ->filter(fn ($value, $id) => abs($value - ($variantsBefore[$id] ?? 0.0)) > 0.0001)
                ->count();

            if (abs($after - $before) > 0.0001 || $movedVariants > 0) {
                $changed[] = [$item->name, number_format($before, 4), number_format($after, 4), $movedVariants];
            }
        }

        return $changed;
    }

    private function variantAverages(string $itemId)
    {
        return ItemVariant::query()
            ->withoutGlobalScopes()
            ->where('item_id', $itemId)
            ->pluck('avg_cost', 'id')
            ->map(fn ($value) => (float) $value);
    }
}
