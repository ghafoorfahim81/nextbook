<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CurrencyStoreRequest;
use App\Http\Requests\Administration\CurrencyUpdateRequest;
use App\Http\Resources\Administration\CurrencyCollection;
use App\Http\Resources\Administration\CurrencyResource;
use App\Models\Administration\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CurrencyController extends Controller
{
    public function index(Request $request)
    {
        
    }

    public function store(CurrencyStoreRequest $request): Response
    {
        $currency = Currency::create($request->validated());

        return new CurrencyResource($currency);
    }

    public function show(Request $request, Currency $currency): Response
    {
        return new CurrencyResource($currency);
    }

    public function update(CurrencyUpdateRequest $request, Currency $currency): Response
    {
        $currency->update($request->validated());

        return new CurrencyResource($currency);
    }

    public function destroy(Request $request, Currency $currency): Response
    {
        $currency->delete();

        return response()->noContent();
    }
}
