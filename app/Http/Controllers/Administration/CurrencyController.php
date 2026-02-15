<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CurrencyStoreRequest;
use App\Http\Requests\Administration\CurrencyUpdateRequest;
use App\Http\Resources\Administration\CurrencyResource;
use App\Models\Administration\Currency;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class CurrencyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Currency::class, 'currency');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', 10);
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $currencies = Currency::search($request->query('search'))
            ->where('is_active', true)
            ->orderBy($sortField, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();
        return inertia('Administration/Currencies/Index', [
            'currencies' => CurrencyResource::collection($currencies),
        ]);
    }

    public function store(CurrencyStoreRequest $request)
    {
        $currency = Currency::create($request->validated());
        return redirect()->route('currencies.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.currency')]));

    }

    public function show(Request $request, Currency $currency): Response
    {
        return new CurrencyResource($currency);
    }

    public function update(CurrencyUpdateRequest $request, Currency $currency)
    {
        $currency->update($request->validated());

        return redirect()->route('currencies.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.currency')]));

    }

    public function destroy(Request $request, Currency $currency)
    {
        $currency->delete();
        return back();
    }
    public function restore(Request $request, Currency $currency)
    {
        $currency->restore();
        return redirect()->route('currencies.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.currency')]));
    }
}
