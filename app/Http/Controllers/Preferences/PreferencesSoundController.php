<?php

namespace App\Http\Controllers\Preferences;

use App\Http\Controllers\Controller;
use App\Support\Inertia\CacheKey;
use App\Support\Preferences\SoundOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class PreferencesSoundController extends Controller
{
    /**
     * Upload a custom sound for the notification/warning/login slot and make it the active choice.
     */
    public function store(Request $request, string $category)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimetypes:audio/mpeg,audio/mp3,audio/wav,audio/x-wav,audio/wave,audio/ogg', 'max:2048'],
        ]);

        $user = $request->user();
        $oldPath = $user->getPreference("notifications.sound.{$category}_custom_path");

        $path = $request->file('file')->store("sounds/{$user->id}", 'public');

        $user->setPreference("notifications.sound.{$category}_custom_path", $path);
        $user->setPreference("notifications.sound.{$category}_custom_url", Storage::disk('public')->url($path));
        $user->setPreference("notifications.sound.{$category}_choice", 'custom');
        $user->save();

        if ($oldPath && Storage::disk('public')->exists($oldPath)) {
            Storage::disk('public')->delete($oldPath);
        }

        Cache::forget(CacheKey::forUser($request, 'preferences'));

        return redirect()->back()->with('success', __('preferences.preferences_saved'));
    }

    /**
     * Remove the custom sound for a slot and fall back to the built-in default.
     */
    public function destroy(Request $request, string $category)
    {
        $user = $request->user();
        $path = $user->getPreference("notifications.sound.{$category}_custom_path");

        if ($path && Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
        }

        $user->setPreference("notifications.sound.{$category}_custom_path", null);
        $user->setPreference("notifications.sound.{$category}_custom_url", null);

        if ($user->getPreference("notifications.sound.{$category}_choice") === 'custom') {
            $user->setPreference("notifications.sound.{$category}_choice", SoundOptions::defaultFor($category));
        }

        $user->save();

        Cache::forget(CacheKey::forUser($request, 'preferences'));

        return redirect()->back()->with('success', __('preferences.preferences_saved'));
    }
}
