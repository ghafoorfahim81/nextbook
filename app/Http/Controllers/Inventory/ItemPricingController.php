<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use App\Models\Inventory\ItemVariant;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ItemPricingController extends Controller
{
    /** Opening the screen with the whole catalogue is a slow page nobody asked for. */
    private const DEFAULT_PER_PAGE = 10;

    private const PER_PAGE_CHOICES = [10, 25, 50, 100];

    public function index(Request $request)
    {
        $this->authorize('viewAny', Item::class);

        $search = trim((string) $request->get('search', ''));
        $perPage = (int) $request->get('perPage', self::DEFAULT_PER_PAGE);

        if (! in_array($perPage, self::PER_PAGE_CHOICES, true)) {
            $perPage = self::DEFAULT_PER_PAGE;
        }

        // Price lives on the variant now, so the variant is the row: a shirt in
        // three sizes is three prices, not one.
        $variants = ItemVariant::query()
            ->with(['item:id,name,code,barcode,is_expiry_tracked,avg_cost'])
            ->where('is_active', true)
            ->when($search !== '', fn ($q) => $q->where(function ($q) use ($search) {
                $like = "%{$search}%";

                $q->whereRaw('LOWER(sku) LIKE LOWER(?)', [$like])
                    ->orWhereRaw('LOWER(barcode) LIKE LOWER(?)', [$like])
                    ->orWhereRaw('LOWER(name) LIKE LOWER(?)', [$like])
                    ->orWhereHas('item', fn ($itemQuery) => $itemQuery->where(function ($i) use ($like) {
                        foreach (['name', 'code', 'barcode', 'sku', 'fast_search', 'generic_name', 'packing', 'rack_no'] as $column) {
                            $i->orWhereRaw("LOWER({$column}) LIKE LOWER(?)", [$like]);
                        }
                    }));
            }))
            ->withSum('stockBalances as on_hand', 'quantity')
            ->withMin('stockBalances as earliest_expiry', 'expire_date')
            // Newest first without a search: the thing just created is the thing
            // whose price is missing. Alphabetical once the operator is looking
            // for something specific.
            ->when($search === '', fn ($q) => $q->orderByDesc('created_at')->orderByDesc('id'))
            ->when($search !== '', fn ($q) => $q->orderBy('name'))
            ->paginate($perPage)
            ->withQueryString()
            ->through(fn (ItemVariant $variant) => $this->presentVariant($variant));

        return inertia('Inventories/Pricing/Index', [
            'items' => $variants,
            'filters' => [
                'search' => $search,
                'perPage' => $perPage,
            ],
            'perPageChoices' => self::PER_PAGE_CHOICES,
        ]);
    }

    /**
     * Save every price the operator changed in one request.
     *
     * The old screen sent one PATCH per row, so repricing a shelf of twenty
     * items was twenty round trips and twenty chances to half-finish.
     */
    public function bulkUpdate(Request $request)
    {
        $this->authorize('update', Item::class);

        $validated = $request->validate([
            'prices' => ['required', 'array', 'min:1'],
            'prices.*.variant_id' => ['required', 'string', 'exists:item_variants,id'],
            'prices.*.sale_price' => ['required', 'numeric', 'min:0'],
        ]);

        $rows = collect($validated['prices'])->keyBy('variant_id');

        $updated = DB::transaction(function () use ($rows) {
            $variants = ItemVariant::query()
                ->with('item')
                ->whereIn('id', $rows->keys())
                ->get();

            foreach ($variants as $variant) {
                $variant->sale_price = (float) $rows[$variant->id]['sale_price'];
                $variant->save();

                // Sales, the POS and barcode labels still read items.sale_price
                // for a simple product. Mirroring the default keeps them right
                // until those callers move to the variant.
                if ($variant->is_default && $variant->item) {
                    $variant->item->forceFill(['sale_price' => $variant->sale_price])->save();
                }
            }

            return $variants->count();
        });

        return back()->with('success', __('general.prices_updated', ['count' => $updated]));
    }

    private function presentVariant(ItemVariant $variant): array
    {
        $item = $variant->item;

        // displayName() falls back to the item's own name when a variant has no
        // name and no attributes — which is exactly the lone default variant of
        // a plain product. That row needs no second label.
        $variantLabel = (string) $variant->displayName();

        if ($variantLabel === (string) $item?->name) {
            $variantLabel = '';
        }

        return [
            'id' => $variant->id,
            'item_id' => $variant->item_id,
            'name' => $item?->name,
            'code' => $variant->sku ?: $item?->code,
            'barcode' => $variant->barcode ?: $item?->barcode,
            'variant_label' => $variantLabel,
            'on_hand' => (float) ($variant->on_hand ?? 0),
            'sale_price' => $variant->sale_price,
            'purchase_price' => $variant->purchase_price,
            // avg_cost is maintained per item by StockService; the variant column
            // only fills in once per-variant costing lands.
            'avg_cost' => $variant->avg_cost ?: $item?->avg_cost,
            'is_expiry_tracked' => (bool) $item?->is_expiry_tracked,
            'earliest_expiry' => $variant->earliest_expiry,
            'expiry_status' => $this->resolveExpiryStatus(
                (bool) $item?->is_expiry_tracked,
                $variant->earliest_expiry
            ),
        ];
    }

    private function resolveExpiryStatus(bool $isExpiryTracked, mixed $earliestExpiry): ?string
    {
        if (!$isExpiryTracked || !$earliestExpiry) {
            return null;
        }

        $today = Carbon::today();
        $expiry = Carbon::parse($earliestExpiry);

        if ($expiry->lt($today)) {
            return 'expired';
        }

        if ($today->diffInDays($expiry) <= 30) {
            return 'expiring_soon';
        }

        return 'ok';
    }
}
