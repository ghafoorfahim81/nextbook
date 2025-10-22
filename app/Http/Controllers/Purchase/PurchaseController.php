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
        return inertia('Purchase/Purchases/Create');
    }

    public function store(PurchaseStoreRequest $request, TransactionService $transactionService, StockService $stockService)
    {

        // dd($request->validated());
        // dd($request->all());
        // return $request->all();
        $purchase = DB::transaction(function () use ($request, $transactionService, $stockService) {
            // Create purchase
            $validated = $request->validated();
            $validated['type']  = $request->sale_purchase_type_id;
            $validated['status'] = 'pending';
            $purchase = Purchase::create(attributes: $validated);
            $purchase->items()->createMany($request->items);
            $stockService->addStock($request->items, $request->store_id, 'purchase', $purchase->id);
            // Create accounting transactions
            $transactions = $transactionService->createPurchaseTransactions(
                $purchase,
                \App\Models\Ledger\Ledger::find($request->supplier_id),
                $request->transaction_total,
                $request->sale_purchase_type_id, // 'cash' or 'credit'
                $request->payment,
                $request->currency_id,
                $request->rate,
            );
            return $purchase;
        });

        // return response()->json($purchase, 201);
    }

    public function store3(PurchaseStoreRequest $request, TransactionService $transactionService)
    {

        DB::transaction(function () use ($request) {
            $purchase = Purchase::create($request->validated());

            $transactionService = app(TransactionService::class);
            $purchaseTransactionService = app(PurchaseTransactionService::class);

            // Create the accounting transactions
            $transactions = $purchaseTransactionService->createPurchaseTransactions(
                $purchase,
                $request->supplier_id,
                $request->payment_method
            );

            foreach ($transactions as $transactionData) {
                $transactionService->createTransaction($transactionData);
            }

            // Store main transaction reference if needed
            $purchase->update(['transaction_id' => $transactions[0]->id]);
        });

        $purchase = DB::transaction(function () use ($request, $transactionService) {
            // Create purchase
            $purchase = Purchase::create($request->validated());
            $purchase->items()->createMany($request->items);
            // HIGH PERFORMANCE: Direct transaction creation
            $transaction = $transactionService->createTransaction([
                'account_id' => Account::where('name', 'Inventory asset')->first()->id,
                'ledger_id' => $request->ledger_id,
                'amount' => $purchase->total_amount - $this->calculateDiscount($purchase),
                'currency_id' => $request->currency_id,
                'date' => $purchase->date,
                'type' => $request->type === 'credit' ? 'credit' : 'debit',
                'remark' => "Purchase #{$purchase->number}",
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
            ]);

            // $purchase->update(['transaction_id' => $transaction->id]);

            return $purchase;
        });
        return redirect()->route('purchases.index')->with('success', 'Purchase created successfully.');
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

        return new PurchaseResource($purchase);
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
