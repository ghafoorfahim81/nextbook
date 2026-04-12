<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CurrencyStoreRequest;
use App\Http\Requests\Administration\CurrencyUpdateRequest;
use App\Http\Resources\Administration\CurrencyResource;
use App\Models\Administration\Currency;
use App\Support\Inertia\CacheForget;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use App\Support\Inertia\CacheKey;
class CurrencyController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Currency::class, 'currency');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'id');
        $sortDirection = $request->input('sortDirection', 'desc');

        $currencies = Currency::with(['createdBy', 'updatedBy'])
            ->search($request->query('search'))
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
        Currency::create($request->currencyData());
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'currencies'));

        return redirect()->route('currencies.index')->with('success', __('general.created_successfully', ['resource' => __('general.resource.currency')]));

    }

    public function show(Request $request, Currency $currency): CurrencyResource
    {
        $currency->load(['createdBy', 'updatedBy']);
        return new CurrencyResource($currency);
    }

    public function update(CurrencyUpdateRequest $request, Currency $currency)
    {
        $currency->update($request->validated());
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'currencies'));

        return redirect()->route('currencies.index')->with('success', __('general.updated_successfully', ['resource' => __('general.resource.currency')]));

    }

    public function destroy(Request $request, Currency $currency)
    {
        $currency->delete();
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'currencies'));

        return back();
    }
    public function restore(Request $request, Currency $currency)
    {
        $currency->restore();
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'currencies'));

        return redirect()->route('currencies.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.currency')]));
    }

    public function forceDelete(Request $request, Currency $currency)
    {
        app(\App\Services\DeletedRecordService::class)->forceDelete('currencies', (string) $currency->id);
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'currencies'));

        return redirect()->route('currencies.index')->with('success', __('general.permanently_deleted_successfully', ['resource' => __('general.resource.currency')]));
    }

    private function forgetCurrencyLookups(Request $request): void
    {
        CacheForget::lookup($request, 'currencies');
        CacheForget::lookup($request, 'home_currency');
    }
}
