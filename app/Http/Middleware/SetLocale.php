<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

class SetLocale
{
    public const SESSION_KEY = 'locale';
    public const COOKIE_KEY = 'locale';

    /**
     * Resolve locale in this order:
     * - authenticated user users.locale
     * - session locale
     * - cookie locale
     * - fallback config('app.locale')
     */
    public function handle(Request $request, Closure $next)
    {
        $locale =
            $this->normalizeLocale(Auth::user()?->locale)
            ?? $this->normalizeLocale($request->session()->get(self::SESSION_KEY))
            ?? $this->normalizeLocale($request->cookie(self::COOKIE_KEY))
            ?? $this->normalizeLocale(config('app.locale'))
            ?? 'en';

        app()->setLocale($locale);

        // Keep session/cookie in sync for both guests and authenticated users.
        $request->session()->put(self::SESSION_KEY, $locale);

        // Long-lived cookie (5 years).
        Cookie::queue(Cookie::make(self::COOKIE_KEY, $locale, 60 * 24 * 365 * 5));

        return $next($request);
    }

    private function normalizeLocale(?string $locale): ?string
    {
        if (!$locale) {
            return null;
        }

        $locale = strtolower(trim($locale));

        // Back-compat alias: 'pa' was used historically for Pashto.
        if ($locale === 'pa') {
            $locale = 'ps';
        }

        // Only allow locales we actually ship translations for.
        $allowed = ['en', 'fa', 'ps'];

        return in_array($locale, $allowed, true) ? $locale : null;
    }
}

