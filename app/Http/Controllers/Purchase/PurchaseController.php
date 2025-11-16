<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Models\Purchase\Purchase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Services\TransactionService;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Services\StockService;

class PurchaseController extends Controller
{
    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $purchases = Purchase::with('supplier')
            ->search($request->query('search'))
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Purchase/Purchases/Index', [
            'purchases' => PurchaseResource::collection($purchases),
        ]);
    }

    public function create(Request $request)
    {
        $purchaseNumber = Purchase::max('number') ? Purchase::max('number') + 1 : 1;

        return inertia('Purchase/Purchases/Create', [
            'purchaseNumber' => $purchaseNumber,
        ]);
    }

    public function store(PurchaseStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    {
        $purchase = DB::transaction(function () use ($request, $transactionService, $stockService) {
            // Create purchase
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            $validated['type']  = $validated['sale_purchase_type_id'] ?? null;
            $validated['status'] = 'pending';

            // Convert date properly
            $validated['date'] = $dateConversionService->toGregorian($validated['date']);

            $purchase = Purchase::create($validated);
            $validated['item_list'] = array_map(function ($item) use ($dateConversionService) {
                $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                $item['discount'] = $item['item_discount'] ? $item['item_discount'] : 0;
                return $item;
            }, $validated['item_list']);
            $purchase->items()->createMany($validated['item_list']);

            foreach ($validated['item_list'] as $item) {
                $stockService->addStock($item, $validated['store_id'], Purchase::class, $purchase->id, $validated['date']);
            }

            // Create accounting transactions
            $transactions = $transactionService->createPurchaseTransactions(
                $purchase,
                \App\Models\Ledger\Ledger::find($validated['supplier_id']),
                $validated['transaction_total'],
                $validated['sale_purchase_type_id'] ?? 'cash',
                $validated['payment'] ?? [],
                $validated['currency_id'] ?? null,
                $validated['rate'] ?? null,
            );

            return $purchase;
        });

        if ((bool) $request->create_and_new) {
            // Stay on the same page; frontend will reset form and increment number
            return redirect()->back()->with('success', 'Purchase created successfully.');
        }

        return redirect()->route('purchases.index')->with('success', 'Purchase created successfully.');
    }


    public function show(Request $request, Purchase $purchase)
    {
        $purchase->load(['items.item', 'items.unitMeasure', 'supplier', 'transaction.currency']);

        return response()->json([
            'data' => new PurchaseResource($purchase),
        ]);
    }

    public function edit(Request $request, Purchase $purchase)
    {
        return inertia('Purchase/Purchases/Edit', [
            'purchase' => new PurchaseResource($purchase->load(['items', 'supplier', 'transaction', 'stocks'])),
        ]);
    }

    public function update(PurchaseUpdateRequest $request, Purchase $purchase, TransactionService $transactionService, StockService $stockService)
    {

        $purchase = DB::transaction(function () use ($request, $purchase, $transactionService, $stockService) {
            $dateConversionService = app(\App\Services\DateConversionService::class);
            $validated = $request->validated();

            // Convert date properly
            if (isset($validated['date'])) {
                $validated['date'] = $dateConversionService->toGregorian($validated['date']);
            }

            $validated['type'] = $validated['sale_purchase_type_id'] ?? $purchase->type;

            // Update main purchase record
            $purchase->update($validated);

            // Handle items if they are being updated
            if (isset($validated['item_list'])) {
                // Remove old stock entries
                // Delete old items and create new ones
                $purchase->items()->forceDelete();
                $validated['item_list'] = array_map(function ($item) use ($dateConversionService) {
                    $item['expire_date'] = $item['expire_date'] ? $dateConversionService->toGregorian($item['expire_date']) : null;
                    $item['discount'] = $item['discount'] ? $item['discount'] : 0;
                    return $item;
                }, $validated['item_list']);
                $purchase->items()->createMany($validated['item_list']);
                $purchase->stock()->forceDelete();
                // Add new stock entries
                foreach ($validated['item_list'] as $item) {
                    $stockService->addStock($item, $validated['store_id'], Purchase::class, $purchase->id, $validated['date']);
                }
            }

            $purchase->transaction()->forceDelete();
            // Update transactions if payment details changed
            $transactions = $transactionService->createPurchaseTransactions(
                $purchase,
                \App\Models\Ledger\Ledger::find($validated['supplier_id']),
                $validated['transaction_total'],
                $validated['sale_purchase_type_id'] ?? 'cash',
                $validated['payment'] ?? [],
                $validated['currency_id'] ?? null,
                $validated['rate'] ?? null,
            );

            return $purchase;
        });

        return redirect()->route('purchases.index')->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Request $request, Purchase $purchase)
    {

        $purchase->items()->delete();
        $purchase->stocks()->delete();
        $purchase->transaction()->delete();
        $purchase->delete();
        return redirect()->route('purchases.index')->with('success', __('general.purchase_deleted_successfully'));

    }
    public function restore(Request $request, Purchase $purchase)
    {

        $purchase->restore();
        $purchase->items()->restore();
        $purchase->stocks()->restore();
        $purchase->transaction()->restore();
        return redirect()->route('purchases.index')->with('success', __('general.purchase_restored_successfully'));
    }

    public function updatePurchaseStatus(Request $request, Purchase $purchase)
    {
        $purchase->update(['status' => $request->status]);
        return back()->with('success', __('general.purchase_status_updated_successfully'));
    }
}
