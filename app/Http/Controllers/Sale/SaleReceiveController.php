<?php

namespace App\Http\Controllers\Sale;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sale\SaleReceiveStoreRequest;
use App\Http\Requests\Sale\SaleReceiveUpdateRequest;
use App\Http\Resources\Sale\SaleReceiveResource;
use App\Models\Sale\SaleReceive;
use Illuminate\Http\Request;

class SaleReceiveController extends Controller
{
    public function index(Request $request)
    {
        $saleReceives = SaleReceive::all();

        return SaleReceiveResource::collection($saleReceives);
    }

    public function store(SaleReceiveStoreRequest $request)
    {
        $saleReceive = SaleReceive::create($request->validated());

        return SaleReceiveResource::make($saleReceive)->response()->setStatusCode(201);
    }

    public function show(Request $request, SaleReceive $saleReceive)
    {
        return SaleReceiveResource::make($saleReceive);
    }

    public function update(SaleReceiveUpdateRequest $request, SaleReceive $saleReceive)
    {
        $saleReceive->update($request->validated());

        return SaleReceiveResource::make($saleReceive);
    }

    public function destroy(Request $request, SaleReceive $saleReceive)
    {
        $saleReceive->delete();

        return response()->noContent();
    }
}
