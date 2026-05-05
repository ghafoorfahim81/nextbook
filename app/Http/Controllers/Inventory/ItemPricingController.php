<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Item;
use Carbon\Carbon;
use Illuminate\Http\Request;

class ItemPricingController extends Controller
{
    public function index(Request $request)
    {
        $this->authorize('viewAny', Item::class);

        $search = $request->get('search');

        $items = Item::query()
            ->when($search, function ($q) use ($search) {
                $q->where(function ($q) use ($search) {
                    $q->where('name', 'like', "%{$search}%")
                        ->orWhere('code', 'like', "%{$search}%")
                        ->orWhere('fast_search', 'like', "%{$search}%")
                        ->orWhere('generic_name', 'like', "%{$search}%")
                        ->orWhere('packing', 'like', "%{$search}%")
                        ->orWhere('sku', 'like', "%{$search}%") 
                        ->orWhere('colors', 'like', "%{$search}%")
                        ->orWhere('rack_no', 'like', "%{$search}%")
                        ->orWhere('barcode', 'like', "%{$search}%");
                });
            })
            ->withSum('stockBalances as on_hand', 'quantity')
            ->withMin('stockBalances', 'expire_date')
            ->orderBy('name')
            ->paginate(20)
            ->through(function ($item) {
                return [
                    'id' => $item->id,
                    'name' => $item->name,
                    'code' => $item->code,
                    'barcode' => $item->barcode,
                    'on_hand' => $item->on_hand ?? 0,
                    'sale_price' => $item->sale_price,
                    'purchase_price' => $item->purchase_price,
                    'avg_cost' => $item->avg_cost,
                    'is_expiry_tracked' => $item->is_expiry_tracked,
                    'earliest_expiry' => $item->stock_balances_min_expire_date,
                    'expiry_status' => $this->resolveExpiryStatus(
                        $item->is_expiry_tracked,
                        $item->stock_balances_min_expire_date
                    ),
                ];
            });

        return inertia('Inventories/Pricing/Index', [
            'items' => $items,
            'filters' => ['search' => $search],
        ]);
    }

    public function update(Request $request, Item $item)
    {
        $this->authorize('update', $item);

        $validated = $request->validate([
            'sale_price' => ['required', 'numeric', 'min:0'],
        ]);

        $item->update(['sale_price' => $validated['sale_price']]);

        return back()->with('success', __('general.update_success', ['name' => __('item.item')]));
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
