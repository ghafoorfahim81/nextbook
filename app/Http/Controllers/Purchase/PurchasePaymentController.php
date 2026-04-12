<?php

namespace App\Http\Controllers\Purchase;

use App\Http\Controllers\Controller;
use App\Http\Requests\Purchase\PurchasePaymentStoreRequest;
use App\Http\Requests\Purchase\PurchasePaymentUpdateRequest;
use App\Http\Resources\Purchase\PurchasePaymentCollection;
use App\Http\Resources\Purchase\PurchasePaymentResource;
use App\Models\Purchase\PurchasePayment;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class PurchasePaymentController extends Controller
{
    public function index(Request $request): Response
    {
        $purchasePayments = PurchasePayment::all();

        return new PurchasePaymentCollection($purchasePayments);
    }

    public function store(PurchasePaymentStoreRequest $request): Response
    {
        $purchasePayment = PurchasePayment::create($request->validated());

        return new PurchasePaymentResource($purchasePayment);
    }

    public function show(Request $request, PurchasePayment $purchasePayment): Response
    {
        return new PurchasePaymentResource($purchasePayment);
    }

    public function update(PurchasePaymentUpdateRequest $request, PurchasePayment $purchasePayment): Response
    {
        $purchasePayment->update($request->validated());

        return new PurchasePaymentResource($purchasePayment);
    }

    public function destroy(Request $request, PurchasePayment $purchasePayment): Response
    {
        $purchasePayment->delete();

        return response()->noContent();
    }

    public function forceDelete(Request $request, PurchasePayment $purchasePayment): Response
    {
        $purchasePayment->forceDelete();

        return response()->noContent();
    }
}
