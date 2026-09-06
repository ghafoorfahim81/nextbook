<?php

namespace App\Http\Controllers;

use App\Support\Inertia\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OnboardingController extends Controller
{
    /**
     * Mark the one-time "check the user manual" prompt as handled — either the
     * user opened the manual or dismissed the welcome dialog. Writes a single
     * key onto the raw preferences JSON so it survives future default merges
     * without bloating the stored value.
     */
    public function dismissManualPrompt(Request $request)
    {
        $user = $request->user();

        $preferences = $user->preferences ?? [];
        data_set($preferences, 'onboarding.manual_prompt_dismissed_at', now()->toIso8601String());
        $user->update(['preferences' => $preferences]);

        Cache::forget(CacheKey::forUser($request, 'preferences'));

        // Called fire-and-forget over XHR from the welcome dialog and the
        // manual page; nothing needs to change on the current screen.
        return response()->noContent();
    }
}
