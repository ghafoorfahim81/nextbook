<?php

namespace App\Http\Controllers;

use App\Support\Inertia\CacheKey;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

class OnboardingController extends Controller
{
    /**
     * The one-time hints the app can show and remember as dismissed. Each maps
     * to `preferences.onboarding.{key}_dismissed_at` on the raw preferences JSON,
     * so the flag survives future default merges without bloating the value.
     */
    private const HINTS = ['manual_prompt', 'item_form_help'];

    /**
     * Mark the app-wide "check the user manual" prompt as handled. Kept as its
     * own route for the welcome dialog and the manual page.
     */
    public function dismissManualPrompt(Request $request)
    {
        return $this->markDismissed($request, 'manual_prompt');
    }

    /**
     * Mark any one-time hint as handled — the item-form instruction modal, etc.
     * Called fire-and-forget over XHR; nothing changes on the current screen.
     */
    public function dismissHint(Request $request)
    {
        $key = (string) $request->input('key');

        abort_unless(in_array($key, self::HINTS, true), 422);

        return $this->markDismissed($request, $key);
    }

    private function markDismissed(Request $request, string $key)
    {
        $user = $request->user();

        $preferences = $user->preferences ?? [];
        data_set($preferences, "onboarding.{$key}_dismissed_at", now()->toIso8601String());
        $user->update(['preferences' => $preferences]);

        Cache::forget(CacheKey::forUser($request, 'preferences'));

        return response()->noContent();
    }
}
