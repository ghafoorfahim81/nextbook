<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Http\Requests\Inventory\FastOpeningRequest;
use App\Models\Inventory\Item;
use App\Enums\StockMovementType;
use App\Enums\StockStatus;
use App\Enums\StockSourceType;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Carbon\Carbon;
use App\Services\ItemVariantService;
use App\Services\StockService;
use App\Services\TransactionService;
use App\Support\BranchContext;

class FastOpeningController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());

        // Fetch items with their related units and other necessary details
        $items = Item::with('unitMeasure')
            ->whereDoesntHave('openings') // Only items with NO opening
            ->orderBy('created_at','desc')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Inventories/Items/FastOpening', [
            'items'        => $items,
        ]);
    }

    public function Store(FastOpeningRequest $request)
    {
        $validated = $request->validated();

        // Only keep items that have quantity > 0
        $rows = collect($validated['items'])
            ->filter(fn ($row) => (float) ($row['quantity'] ?? 0) > 0)
            ->values();

        if ($rows->isEmpty()) {
            return back()->with('warning', __('general.nothing_to_save'));
        }

        $posted = 0;

        DB::transaction(function () use ($rows, &$posted) {
            $glAccounts = BranchContext::glAccounts();
            $homeCurrency = BranchContext::homeCurrency();
            $stockService = app(StockService::class);
            $transactionService = app(TransactionService::class);
            $variantService = app(ItemVariantService::class);
            $date = Carbon::now()->toDateString();

            // One query for the whole batch. The old code re-read the item from
            // the database seven times per row, purely to print its name.
            $items = Item::query()
                ->whereIn('id', $rows->pluck('item_id')->unique()->all())
                ->get()
                ->keyBy('id');

            foreach ($rows as $row) {
                $item = $items->get($row['item_id']);

                if (! $item) {
                    continue;
                }

                // The list is built from items that have no opening yet, but the
                // page may have been sitting open while someone else posted one.
                // Re-checking here keeps a stale tab from doubling the stock.
                if ($item->openings()->exists()) {
                    continue;
                }

                $quantity = (float) $row['quantity'];
                $unitCost = (float) ($row['cost'] ?? 0);

                // Fast opening can run against items created outside the item
                // form, so the default variant may still be missing.
                $variant = $variantService->ensureDefault($item);

                $stockService->post([
                    'item_id'         => $item->id,
                    // Without this the movement and its balance row carry a null
                    // variant_id while every other entry point sets one.
                    'variant_id'      => $variant->id,
                    'movement_type'   => StockMovementType::IN->value,
                    'unit_measure_id' => $row['unit_measure_id'], // from item form
                    'quantity'        => $quantity,
                    'source'          => StockSourceType::OPENING->value,
                    'unit_cost'       => $unitCost,
                    'status'          => StockStatus::DRAFT->value,
                    'batch'           => $row['batch'] ?? null,
                    'date'            => $date,
                    'expire_date'     => $row['expire_date'] ?? null,
                    'warehouse_id'    => $row['warehouse_id'],
                    // This used to read `auth()->user()->company->branch_id`, and
                    // companies have no branch_id column — so it was always null.
                    // The write survived only because the HasBranch boot hook fills
                    // a blank branch_id in, which means the balance lookup ran
                    // against a null branch and matched nothing on the way past.
                    'branch_id'       => $item->branch_id,
                ]);

                $posted++;

                $openingValue = round($unitCost * $quantity, 4);

                // A zero-value opening would post a voucher that balances at
                // nothing and only clutters the ledger.
                if ($openingValue <= 0) {
                    continue;
                }

                $transactionService->post(
                    header: [
                        'currency_id' => $homeCurrency->id,
                        'rate' => 1,
                        'voucher_number' => 'Opening Balance ' . $item->name . ' #' . $item->code,
                        'date' => $date,
                        'reference_type' => Item::class,
                        'reference_id' => $item->id,
                        'remark' => 'Opening balance for item ' . $item->name,
                    ],
                    lines: [
                        [
                            // An item created outside the item form can have no
                            // asset account; a null account_id fails validation
                            // and takes the whole batch down with it.
                            'account_id' => $item->asset_account_id ?? $glAccounts['inventory-stock'],
                            'debit' => $openingValue,
                            'credit' => 0,
                            'remark' => 'Opening balance for item ' . ' ' . $item->name,
                            'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                            'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                        ],
                        [
                            'account_id' => $glAccounts['opening-balance-equity'],
                            'debit' => 0,
                            'credit' => $openingValue,
                            'remark' => 'Opening balance for item ' . ' ' . $item->name,
                            'remark_fa' => 'موجودی اولیه برای جنس ' . ' ' . $item->name,
                            'remark_ps' =>'د'. ' '. $item->name.' '.'د پرانیستلو بیلانس ',
                        ]
                    ]
                );
            }
        });

        // The old store() returned nothing at all, so Inertia got an empty 200
        // back and the page had to fall back to a full window.location.reload().
        return redirect()
            ->route('item.fast.opening')
            ->with('success', __('general.items_saved_successfully'));
    }
}
