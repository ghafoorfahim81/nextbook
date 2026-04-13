<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchasePaymentStoreRequest;
use App\Http\Requests\Purchase\PurchasePaymentUpdateRequest;
use App\Http\Resources\Purchase\PurchasePaymentResource;
use App\Models\Purchase\PurchasePayment;
use Illuminate\Http\Request;

class PurchasePaymentController extends Controller
{
    public function index(Request $request)
    {
        $purchasePayments = PurchasePayment::all();

        return PurchasePaymentResource::collection($purchasePayments);
    }

    public function store(PurchasePaymentStoreRequest $request)
    {
        $purchasePayment = PurchasePayment::create($request->validated());

        return PurchasePaymentResource::make($purchasePayment)->response()->setStatusCode(201);
    }

    public function show(Request $request, PurchasePayment $purchasePayment)
    {
        return PurchasePaymentResource::make($purchasePayment);
    }

    public function update(PurchasePaymentUpdateRequest $request, PurchasePayment $purchasePayment)
    {
        $purchasePayment->update($request->validated());

        return PurchasePaymentResource::make($purchasePayment);
    }

    public function destroy(Request $request, PurchasePayment $purchasePayment)
    {
        $purchasePayment->delete();

        return response()->noContent();
    }
}
