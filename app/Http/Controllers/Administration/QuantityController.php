<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\QuantityStoreRequest;
use App\Http\Requests\Administration\QuantityUpdateRequest;
use App\Http\Resources\Administration\QuantityCollection;
use App\Http\Resources\Administration\QuantityResource;
use App\Models\Administration\Quantity;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class QuantityController extends Controller
{
    public function index(Request $request): Response
    {
        $quantities = Quantity::all();

        return new QuantityCollection($quantities);
    }

    public function store(QuantityStoreRequest $request): Response
    {
        $quantity = Quantity::create($request->validated());

        return new QuantityResource($quantity);
    }

    public function show(Request $request, Quantity $quantity): Response
    {
        return new QuantityResource($quantity);
    }

    public function update(QuantityUpdateRequest $request, Quantity $quantity): Response
    {
        $quantity->update($request->validated());

        return new QuantityResource($quantity);
    }

    public function destroy(Request $request, Quantity $quantity): Response
    {
        $quantity->delete();

        return response()->noContent();
    }
}
