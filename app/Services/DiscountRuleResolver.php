<?php

namespace App\Services;

use App\Enums\DiscountScope;
use App\Models\Inventory\DiscountRule;
use App\Models\Inventory\Item;
use App\Models\Ledger\Ledger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Picks the one discount rule that applies to a sale line.
 *
 * Most specific wins, never stacks: a rule naming the item beats one naming its
 * category, which beats a blanket rule, and any rule tied to this customer's
 * group beats the same scope without one. A discount typed by hand on the line
 * is not this service's business — the caller only asks when the line has none,
 * so the salesperson always keeps the final say.
 */
class DiscountRuleResolver
{
    /** Rules for the branch, loaded once per request. */
    private ?Collection $cache = null;

    /** ledger id => customer group id, memoised per request. */
    private array $groups = [];

    /**
     * @return DiscountRule|null The winning rule, or null when nothing applies.
     */
    public function resolve(
        string $itemId,
        float $quantity,
        ?string $ledgerId = null,
        ?string $branchId = null,
        Carbon|string|null $onDate = null,
    ): ?DiscountRule {
        return $this->candidatesFor($itemId, $ledgerId, $branchId, $onDate)
            ->first(fn (DiscountRule $rule) => $this->quantityReaches($rule, $quantity));
    }

    /**
     * Every rule that could apply to this item for this customer on this date,
     * best first, with the minimum-quantity gate left open.
     *
     * The sale screen fetches this list once per item and then picks from it as
     * the salesperson types a quantity, so the discount updates without a round
     * trip. Ordering is quantity-independent, which is what makes that safe:
     * take the first entry whose min_quantity the line reaches and you have
     * exactly what resolve() would have returned.
     *
     * @return Collection<int, DiscountRule>
     */
    public function candidatesFor(
        string $itemId,
        ?string $ledgerId = null,
        ?string $branchId = null,
        Carbon|string|null $onDate = null,
    ): Collection {
        $item = Item::query()->select(['id', 'category_id', 'brand_id'])->find($itemId);

        if (! $item) {
            return collect();
        }

        $date = $onDate ? Carbon::parse($onDate)->startOfDay() : Carbon::now()->startOfDay();

        return $this->rulesFor($branchId)
            ->filter(fn (DiscountRule $rule) => $this->applies($rule, $item, $ledgerId, $date))
            ->sortByDesc(fn (DiscountRule $rule) => [
                $rule->specificity(),
                $rule->priority,
                (float) $rule->value,
            ])
            ->values();
    }

    private function quantityReaches(DiscountRule $rule, float $quantity): bool
    {
        return $rule->min_quantity === null || $quantity >= (float) $rule->min_quantity;
    }

    /**
     * The discount amount for a line, or 0.0 when no rule applies. Convenience
     * for callers that only want the number.
     */
    public function discountFor(
        string $itemId,
        float $quantity,
        float $unitPrice,
        ?string $ledgerId = null,
        ?string $branchId = null,
        Carbon|string|null $onDate = null,
    ): float {
        return $this->resolve($itemId, $quantity, $ledgerId, $branchId, $onDate)
            ?->discountFor($quantity, $unitPrice) ?? 0.0;
    }

    /** Drop the per-request caches — for tests and long-running workers. */
    public function flush(): void
    {
        $this->cache = null;
        $this->groups = [];
    }

    /**
     * The customer group a sale's customer belongs to. Looked up once per
     * ledger: a sale of forty lines must not become forty queries.
     */
    private function groupOf(?string $ledgerId): ?string
    {
        if (blank($ledgerId)) {
            return null;
        }

        return $this->groups[$ledgerId] ??= Ledger::query()
            ->whereKey($ledgerId)
            ->value('group_id');
    }

    private function applies(
        DiscountRule $rule,
        Item $item,
        ?string $ledgerId,
        Carbon $date,
    ): bool {
        // A group-scoped rule is for that group only. An ungrouped rule applies
        // to everyone, including members of a group.
        if (filled($rule->customer_group_id) && $rule->customer_group_id !== $this->groupOf($ledgerId)) {
            return false;
        }

        // A missing bound means open-ended in that direction.
        if ($rule->starts_at !== null && $date->lt($rule->starts_at->startOfDay())) {
            return false;
        }

        if ($rule->ends_at !== null && $date->gt($rule->ends_at->startOfDay())) {
            return false;
        }

        return match ($rule->scope) {
            DiscountScope::ALL => true,
            DiscountScope::ITEM => $rule->scope_id === $item->id,
            DiscountScope::CATEGORY => filled($item->category_id) && $rule->scope_id === $item->category_id,
            DiscountScope::BRAND => filled($item->brand_id) && $rule->scope_id === $item->brand_id,
        };
    }

    /**
     * @return Collection<int, DiscountRule>
     */
    private function rulesFor(?string $branchId): Collection
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        return $this->cache = DiscountRule::query()
            ->where('is_active', true)
            ->when($branchId, fn ($query) => $query->where('branch_id', $branchId))
            ->get();
    }
}
