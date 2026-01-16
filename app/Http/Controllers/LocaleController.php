<?php

namespace App\Http\Controllers;

use App\Http\Middleware\SetLocale;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Validation\Rule;

class LocaleController extends Controller
{
    public function __invoke(Request $request)
    {
        $validated = $request->validate([
            'locale' => ['required', 'string', Rule::in(['en', 'fa', 'ps', 'pa'])],
        ]);

        $locale = strtolower(trim($validated['locale']));
        if ($locale === 'pa') {
            $locale = 'ps';
        }

        $request->session()->put(SetLocale::SESSION_KEY, $locale);

        if (Auth::check()) {
            $request->user()->forceFill(['locale' => $locale])->save();
        }

        Cookie::queue(Cookie::make(SetLocale::COOKIE_KEY, $locale, 60 * 24 * 365 * 5));

        return back();
    }
}

