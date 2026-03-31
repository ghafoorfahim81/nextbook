<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CurrencyRateUpdateStoreRequest;
use App\Http\Resources\Administration\CurrencyRateUpdateResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Models\Administration\Currency;
use App\Models\Administration\CurrencyRateUpdate;
use App\Support\Inertia\CacheForget;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CurrencyRateUpdateController extends Controller
{
    private function normalizeRate(mixed $value): string
    {
        return number_format((float) $value, 8, '.', '');
    }

    public function index(Request $request)
    {
        abort_unless($request->user()?->can('currencies.view_any'), 403);

        $homeCurrency = Currency::query()
            ->where('is_base_currency', true)
            ->firstOrFail();

        $currencies = Currency::query()
            ->where('is_active', true)
            ->whereKeyNot($homeCurrency->id)
            ->orderBy('name')
            ->get();

        $perPage = (int) $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'date');
        $sortDirection = $request->input('sortDirection', 'desc');
        $allowedSorts = [
            'date' => 'date',
            'exchange_rate' => 'exchange_rate',
        ];

        $history = CurrencyRateUpdate::query()
            ->with('currency')
            ->search($request->query('search'))
            ->orderBy($allowedSorts[$sortField] ?? 'date', $sortDirection === 'asc' ? 'asc' : 'desc')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Administration/CurrencyRateUpdates/Index', [
            'homeCurrency' => CurrencyResource::make($homeCurrency),
            'currencies' => CurrencyResource::collection($currencies),
            'history' => CurrencyRateUpdateResource::collection($history),
            'filters' => [
                'search' => $request->query('search', ''),
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
            ],
            'effectiveDate' => Carbon::today()->toDateString(),
        ]);
    }

    public function store(CurrencyRateUpdateStoreRequest $request)
    {
        abort_unless($request->user()?->can('currencies.update'), 403);

        $validated = $request->validated();
        $date = Carbon::parse($validated['date'] ?? now())->toDateString();
        $updates = collect($validated['updates']);

        $currencies = Currency::query()
            ->whereIn('id', $updates->pluck('currency_id'))
            ->get()
            ->keyBy('id');

        $changedUpdates = $updates->filter(function ($payload) use ($currencies) {
            $currency = $currencies->get($payload['currency_id']);

            if (!$currency) {
                return true;
            }

            return $this->normalizeRate($payload['exchange_rate']) !== $this->normalizeRate($currency->exchange_rate);
        })->values();

        if ($changedUpdates->isEmpty()) {
            return redirect()->route('currency-rate-updates.index');
        }

        DB::transaction(function () use ($changedUpdates, $currencies, $date) {
            foreach ($changedUpdates as $payload) {
                $currency = $currencies->get($payload['currency_id']);

                if (!$currency) {
                    throw ValidationException::withMessages([
                        'updates' => __('validation.exists', ['attribute' => __('admin.currency.currency')]),
                    ]);
                }

                if ($currency->is_base_currency) {
                    throw ValidationException::withMessages([
                        'updates' => __('admin.currency.home_currency_cannot_be_updated'),
                    ]);
                }

                $currency->update([
                    'exchange_rate' => $this->normalizeRate($payload['exchange_rate']),
                ]);

                CurrencyRateUpdate::create([
                    'currency_id' => $currency->id,
                    'exchange_rate' => $this->normalizeRate($payload['exchange_rate']),
                    'date' => $date,
                ]);
            }
        });

        CacheForget::lookup($request, 'currencies');

        return redirect()
            ->route('currency-rate-updates.index')
            ->with('success', __('general.updated_successfully', ['resource' => __('admin.currency.rate_updates')]));
    }
}
