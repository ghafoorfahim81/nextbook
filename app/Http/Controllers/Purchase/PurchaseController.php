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
            $purchase->items()->createMany($validated['item_list']);

            foreach ($validated['item_list'] as $item) {
                $stockService->addStock($item, $validated['store_id'], 'purchase', $purchase->id, $validated['date']);
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
        return inertia('Purchase/Purchases/Show', [
            'purchase' => new PurchaseResource($purchase),
        ]);
    }

    public function edit(Request $request, Purchase $purchase)
    {
        return inertia('Purchase/Purchases/Edit', [
            'purchase' => new PurchaseResource($purchase->load(['items', 'supplier', 'transaction'])),
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
                foreach ($purchase->items as $oldItem) {
                    $stockService->removeStock([
                        'item_id' => $oldItem->item_id,
                        'quantity' => $oldItem->quantity,
                        'sale_price' => $oldItem->unit_price,
                        'free' => $oldItem->free ?? 0,
                        'tax' => $oldItem->tax ?? 0,
                        'discount' => $oldItem->discount ?? 0,
                        'date_out' => $purchase->date,
                    ], $purchase->store_id, 'purchase', $purchase->id);
                }

                // Delete old items and create new ones
                $purchase->items()->delete();
                $purchase->items()->createMany($validated['item_list']);

                // Add new stock entries
                foreach ($validated['item_list'] as $item) {
                    $stockService->addStock($item, $purchase->store_id, 'purchase', $purchase->id);
                }
            }
            
            // Update transactions if payment details changed
            if (isset($validated['transaction_total']) || isset($validated['payment']) || isset($validated['sale_purchase_type_id'])) {
                $transactionService->updatePurchaseTransactions(
                    $purchase,
                    \App\Models\Ledger\Ledger::find($validated['supplier_id'] ?? $purchase->supplier_id),
                    $validated['transaction_total'] ?? $purchase->transaction_total,
                    $validated['sale_purchase_type_id'] ?? $purchase->type,
                    $validated['payment'] ?? [],
                    $validated['currency_id'] ?? $purchase->currency_id,
                    $validated['rate'] ?? $purchase->rate
                );
            }
            
            return $purchase;
        });
        
        return redirect()->route('purchases.index')->with('success', 'Purchase updated successfully.');
    }

    public function destroy(Request $request, Purchase $purchase): Response
    {
        $purchase->delete();

        return response()->noContent();
    }
    public function restore(Request $request, Purchase $purchase)
    {
        $purchase->restore();
        return redirect()->route('purchases.index')->with('success', 'Purchase restored successfully.');
    }
}
