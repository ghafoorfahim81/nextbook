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

    public function store(PurchaseStoreRequest $request, TransactionService $transactionService)
    {
        $purchase = DB::transaction(function () use ($request, $transactionService) {
            // Create purchase
            $purchase = Purchase::create($request->validated());
            $purchase->items()->createMany($request->items);
            // HIGH PERFORMANCE: Direct transaction creation
            $transaction = $transactionService->createTransaction([
                'account_id' => $request->account_id,
                'ledger_id' => $request->ledger_id,
                'amount' => $purchase->total_amount - $this->calculateDiscount($purchase),
                'currency_id' => $request->currency_id,
                'date' => $purchase->date,
                'type' => $request->type === 'credit' ? 'credit' : 'debit',
                'remark' => "Purchase #{$purchase->number}",
                'reference_type' => 'purchase',
                'reference_id' => $purchase->id,
            ]);

            $purchase->update(['transaction_id' => $transaction->id]);

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
