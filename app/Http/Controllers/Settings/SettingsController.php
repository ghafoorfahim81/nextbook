<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\UpdateSettingsRequest;
use App\Models\Account\Account;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SettingsController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $preferences = $user->getAllPreferences();
        
        // Get accounts for default cash account dropdown
        $cashAccounts = Account::select('id', 'name')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        return Inertia::render('Settings/Index', [
            'preferences' => $preferences,
            'defaultPreferences' => User::DEFAULT_PREFERENCES,
            'cashAccounts' => $cashAccounts,
            'sidebarMenus' => $this->getSidebarMenuOptions(),
            'timezones' => $this->getTimezones(),
        ]);
    }

    public function update(UpdateSettingsRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();
        
        // Merge validated preferences with existing ones
        $currentPreferences = $user->preferences ?? User::DEFAULT_PREFERENCES;
        $newPreferences = array_replace_recursive($currentPreferences, $validated);
        
        $user->update(['preferences' => $newPreferences]);

        return redirect()->back()->with('success', __('settings.settings_saved'));
    }

    public function reset(Request $request, ?string $category = null)
    {
        $user = $request->user();
        $user->resetPreferences($category)->save();

        return redirect()->back()->with('success', __('settings.settings_reset'));
    }

    public function export(Request $request)
    {
        $user = $request->user();
        $preferences = $user->getAllPreferences();

        return response()->json($preferences)
            ->header('Content-Disposition', 'attachment; filename="settings.json"')
            ->header('Content-Type', 'application/json');
    }

    public function import(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:1024',
        ]);

        $content = file_get_contents($request->file('file')->path());
        $preferences = json_decode($content, true);

        if (!is_array($preferences)) {
            return redirect()->back()->with('error', __('settings.invalid_settings_file'));
        }

        $user = $request->user();
        $user->update(['preferences' => array_replace_recursive(User::DEFAULT_PREFERENCES, $preferences)]);

        return redirect()->back()->with('success', __('settings.settings_imported'));
    }

    private function getSidebarMenuOptions(): array
    {
        return [
            ['value' => 'dashboard', 'label' => 'Dashboard'],
            ['value' => 'administration', 'label' => 'Administration'],
            ['value' => 'inventory', 'label' => 'Inventory'],
            ['value' => 'ledger', 'label' => 'Ledger'],
            ['value' => 'owners', 'label' => 'Owners'],
            ['value' => 'account', 'label' => 'Account'],
            ['value' => 'purchase', 'label' => 'Purchase'],
            ['value' => 'sale', 'label' => 'Sale'],
            ['value' => 'receipt', 'label' => 'Receipt'],
            ['value' => 'payment', 'label' => 'Payment'],
            ['value' => 'transfer', 'label' => 'Account Transfers'],
            ['value' => 'user_management', 'label' => 'User Management'],
        ];
    }

    private function getTimezones(): array
    {
        return [
            'UTC' => 'UTC',
            'Asia/Kabul' => 'Asia/Kabul (UTC+4:30)',
            'Asia/Tehran' => 'Asia/Tehran (UTC+3:30)',
            'Asia/Dubai' => 'Asia/Dubai (UTC+4)',
            'Asia/Karachi' => 'Asia/Karachi (UTC+5)',
            'Asia/Kolkata' => 'Asia/Kolkata (UTC+5:30)',
            'Europe/London' => 'Europe/London (UTC+0)',
            'Europe/Paris' => 'Europe/Paris (UTC+1)',
            'America/New_York' => 'America/New_York (UTC-5)',
            'America/Los_Angeles' => 'America/Los_Angeles (UTC-8)',
        ];
    }
}

