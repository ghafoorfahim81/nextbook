<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request)
    {
        $previousLocale = $this->normalizeLocale(
            Auth::user()?->locale
                ?? $request->session()->get(SetLocale::SESSION_KEY)
                ?? $request->cookie(SetLocale::COOKIE_KEY)
                ?? config('app.locale')
        );

        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(['en', 'fa', 'ps', 'pa'])],
        ]);

        $locale = $this->normalizeLocale($validated['locale']) ?? 'en';

        $request->session()->put(SetLocale::SESSION_KEY, $locale);

        if (Auth::check()) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }

        Cookie::queue(Cookie::make(SetLocale::COOKIE_KEY, $locale, 60 * 24 * 365 * 5));

        // Clear locale-dependent enum caches so labels refresh immediately after switching language.
        $this->forgetEnumCachesForLocales(array_unique(array_filter([$previousLocale, $locale])));

        // Inertia: after POST, redirect with 303 so the client performs a GET and refreshes props.
        return back(303);
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        $locale = strtolower(trim($locale));
        if ($locale === 'pa') {
            $locale = 'ps';
        }

        return in_array($locale, ['en', 'fa', 'ps'], true) ? $locale : null;
    }

    /**
     * @param array<int, string> $locales
     */
    private function forgetEnumCachesForLocales(array $locales): void
    {
        foreach ($locales as $locale) {
            Cache::forget('businessTypes_' . $locale);
            Cache::forget('calendarTypes_' . $locale);
            Cache::forget('workingStyles_' . $locale);
            Cache::forget('locales_' . $locale);
            Cache::forget('salePurchaseTypes_' . $locale);
            Cache::forget('discountTypes_' . $locale);
            Cache::forget('transactionStatuses_' . $locale);
            Cache::forget('transaction_types_' . $locale);
        }
    }
}

