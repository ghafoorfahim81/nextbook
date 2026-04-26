<?php

namespace App\Http\Controllers;

use App\Models\Administration\Currency;
use App\Models\Administration\UnitMeasure;
use Illuminate\Container\Attributes\Auth;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();

        // Currencies: scope to the user's branch (BranchSpecific global scope handles this
        // automatically via auth()->user()->branch_id). If the user has no branch_id,
        // fall back to all active currencies for the company.
        $currencyQuery = Currency::query();

        if (! $user?->branch_id) {
            // No branch assigned — remove the branch scope and show all company currencies
            $currencyQuery->withoutGlobalScope('branchSpecific');
        }

        $currencies = $currencyQuery
            ->orderByDesc('is_base_currency')
            ->orderByDesc('is_main')
            ->get(['id', 'name', 'code', 'symbol', 'exchange_rate', 'is_base_currency', 'is_main', 'flag']);

        // Unit measures: same branch-scoped approach
        $unitQuery = UnitMeasure::query()->where('is_active', true);

        if (! $user?->branch_id) {
            $unitQuery->withoutGlobalScope('branchSpecific');
        }

        $authUser = Auth()->user()->company;
        // dd($authUser);
        $unitMeasures = $unitQuery
            ->with('quantity:id,quantity,slug,symbol')
            ->get(['id', 'name', 'unit', 'symbol', 'value', 'is_main', 'quantity_id']);

        return Inertia::render('Home', [
            'currencies'   => $currencies,
            'unitMeasures' => $unitMeasures,
        ]);
    }

    /**
     * Currency exchange calculation using internal rates.
     */
    public function exchange(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'      => 'required|numeric|min:0',
            'from_code'   => 'required|string',
            'to_code'     => 'required|string',
        ]);

        $hasBranch = (bool) $request->user()?->branch_id;

        $from = Currency::query()  
            ->where('code', $validated['from_code'])
            ->first(['code', 'exchange_rate', 'is_base_currency']);

        $to = Currency::query()  
            ->where('code', $validated['to_code'])
            ->first(['code', 'exchange_rate', 'is_base_currency']);

        if (! $from || ! $to) {
            return response()->json(['error' => 'Currency not found'], 404);
        }

        // Normalise to base currency first, then convert to target.
        // exchange_rate stores: how many units of this currency equal 1 base unit.
        // e.g. base = AFN, USD exchange_rate = 0.0145 means 1 AFN = 0.0145 USD
        // So: amount_in_base = amount / from.exchange_rate  (if from is not base)
        //     result = amount_in_base * to.exchange_rate
        // When from IS the base currency its exchange_rate should be 1.
        $fromRate = (float) $from->exchange_rate ?: 1.0;
        $toRate   = (float) $to->exchange_rate   ?: 1.0;

        $amountInBase = $validated['amount'] / $fromRate;
        $result       = $amountInBase * $toRate;

        return response()->json([
            'result'    => round($result, 6),
            'from_code' => $from->code,
            'to_code'   => $to->code,
            'rate'      => round($toRate / $fromRate, 8),
        ]);
    }

    /**
     * Unit conversion using system-stored values.
     */
    public function unitConvert(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'amount'  => 'required|numeric',
            'from_id' => 'required|string',
            'to_id'   => 'required|string',
        ]);

        $hasBranch = (bool) $request->user()?->branch_id;

        $from = UnitMeasure::query()
            ->when(! $hasBranch, fn ($q) => $q->withoutGlobalScope('branchSpecific'))
            ->where('is_active', true)
            ->where('id', $validated['from_id'])
            ->first(['id', 'name', 'symbol', 'value', 'quantity_id']);

        $to = UnitMeasure::query()
            ->when(! $hasBranch, fn ($q) => $q->withoutGlobalScope('branchSpecific'))
            ->where('is_active', true)
            ->where('id', $validated['to_id'])
            ->first(['id', 'name', 'symbol', 'value', 'quantity_id']);

        if (! $from || ! $to) {
            return response()->json(['error' => 'Unit not found'], 404);
        }

        if ($from->quantity_id !== $to->quantity_id) {
            return response()->json(['error' => 'Units belong to different quantity types'], 422);
        }

        $fromValue = (float) $from->value ?: 1.0;
        $toValue   = (float) $to->value   ?: 1.0;

        // value stores the factor relative to the base unit of the quantity.
        // result = amount * (fromValue / toValue)
        $result = $validated['amount'] * ($fromValue / $toValue);

        return response()->json([
            'result'      => round($result, 6),
            'from_symbol' => $from->symbol,
            'to_symbol'   => $to->symbol,
        ]);
    }

    /**
     * Weather data from Open-Meteo (free, no API key required).
     * Step 1: geocode city name → lat/lon via Open-Meteo Geocoding API.
     * Step 2: fetch current + 7-day daily forecast from Open-Meteo Forecast API.
     */
    public function weather(Request $request): JsonResponse
    {
        $city = trim((string) $request->query('city', 'Kabul'));

        try {
            // ── Geocoding ──────────────────────────────────────────────────
            $geoResponse = Http::timeout(8)->get('https://geocoding-api.open-meteo.com/v1/search', [
                'name'    => $city,
                'count'   => 1,
                'language' => 'en',
                'format'  => 'json',
            ]);

            if ($geoResponse->failed() || empty($geoResponse->json('results'))) {
                return response()->json(['error' => 'City not found'], 404);
            }

            $location = $geoResponse->json('results.0');
            $lat      = $location['latitude'];
            $lon      = $location['longitude'];
            $cityName = $location['name'];
            $country  = $location['country_code'] ?? '';
            $timezone = $location['timezone'] ?? 'auto';

            // ── Forecast ───────────────────────────────────────────────────
            $forecastResponse = Http::timeout(10)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude'            => $lat,
                'longitude'           => $lon,
                'current'             => 'temperature_2m,apparent_temperature,relative_humidity_2m,wind_speed_10m,weather_code,is_day',
                'daily'               => 'weather_code,temperature_2m_max,temperature_2m_min',
                'timezone'            => $timezone,
                'forecast_days'       => 7,
            ]);

            if ($forecastResponse->failed()) {
                return response()->json(['error' => 'Weather data unavailable'], 502);
            }

            $data    = $forecastResponse->json();
            $current = $data['current'] ?? [];
            $daily   = $data['daily'] ?? [];

            // Build 7-day forecast array
            $forecast = [];
            $dates    = $daily['time'] ?? [];
            foreach ($dates as $i => $date) {
                $forecast[] = [
                    'date'    => $date,
                    'code'    => $daily['weather_code'][$i] ?? 0,
                    'max'     => $daily['temperature_2m_max'][$i] ?? null,
                    'min'     => $daily['temperature_2m_min'][$i] ?? null,
                ];
            }

            return response()->json([
                'city'        => $cityName,
                'country'     => $country,
                'lat'         => $lat,
                'lon'         => $lon,
                'temp'        => $current['temperature_2m'] ?? null,
                'feels_like'  => $current['apparent_temperature'] ?? null,
                'humidity'    => $current['relative_humidity_2m'] ?? null,
                'wind_speed'  => $current['wind_speed_10m'] ?? null,
                'code'        => $current['weather_code'] ?? 0,
                'is_day'      => ($current['is_day'] ?? 1) === 1,
                'forecast'    => $forecast,
            ]);
        } catch (\Throwable) {
            return response()->json(['error' => 'Failed to fetch weather data'], 502);
        }
    }
}
