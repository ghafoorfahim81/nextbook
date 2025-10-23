<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchaseStoreRequest;
use App\Http\Requests\Purchase\PurchaseUpdateRequest;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Models\Purchase\Purchase;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
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
        $purchaseNumber = Purchase::max('number') ? Purchase::max('number') + 1 : '1';
        return inertia('Purchase/Purchases/Create', [
            'purchaseNumber' => $purchaseNumber,
        ]);
    }

    public function store(PurchaseStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    {

        // dd($request->validated());
        // dd($request->all());
        // return $request->all();
        $purchase = DB::transaction(function () use ($request, $transactionService, $stockService) {
            // Create purchase
            $validated = $request->validated();
            $validated['type']  = $validated['sale_purchase_type_id'] ?? null;
            $validated['status'] = 'pending';
            $purchase = Purchase::create(attributes: $validated);
            $purchase->items()->createMany($validated['items']);
            foreach ($validated['items'] as $item) {
                $stockService->addStock($item, $validated['store_id'], 'purchase', $purchase->id);
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

        // return response()->json($purchase, 201);
    }


    public function show(Request $request, Purchase $purchase)
    {
        return inertia('Purchase/Purchases/Show', [
            'purchase' => new PurchaseResource($purchase),
        ]);
    }

    public function update(PurchaseUpdateRequest $request, Purchase $purchase): Response
    {
        $purchase->update($request->validated());

        return response(new PurchaseResource($purchase));
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
