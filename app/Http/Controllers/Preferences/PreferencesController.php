<?php

namespace App\Http\Controllers\Preferences;

use App\Http\Controllers\Controller;
use App\Http\Requests\Preferences\UpdatePreferencesRequest;
use App\Http\Resources\Administration\CategoryResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Administration\SizeResource;
use App\Http\Resources\Administration\StoreResource;
use App\Models\Account\Account;
use App\Models\Administration\Category;
use App\Models\Administration\Currency;
use App\Models\Administration\Size;
use App\Models\Administration\Store;
use App\Models\Administration\UnitMeasure;
use App\Models\Ledger\Ledger;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Cache;
use App\Support\Inertia\CacheKey;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\Administration\UnitMeasureResource;
use App\Http\Resources\Ledger\LedgerResource;
class PreferencesController extends Controller
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

        $unitMeasures = UnitMeasureResource::collection(
            UnitMeasure::query()
                ->orderBy('name')
                ->get()
        );

        $categories = CategoryResource::collection(Category::query()->orderBy('name')->where('is_active', false)->get());
        $stores = StoreResource::collection(Store::query()->orderBy('name')->where('is_active', false)->get());
        $sizes = SizeResource::collection(Size::query()->orderBy('name')->where('is_active', false)->get());
        $currencies = CurrencyResource::collection(Currency::query()->orderBy('name')->where('is_active', false)->get());

        $ledgers = LedgerResource::collection(
            Ledger::query()
                ->whereIn('type', ['customer', 'supplier'])
                ->orderBy('name')
                ->where('is_active', false)
                ->limit(500)
                ->get()
        );

        return Inertia::render('Preferences/Index', [
            'preferences' => $preferences,
            'defaultPreferences' => User::DEFAULT_PREFERENCES,
            'cashAccounts' => $cashAccounts,
            'sidebarMenus' => $this->getSidebarMenuOptions(),
            'timezones' => $this->getTimezones(),
            'unitMeasures' => $unitMeasures,
            'categories' => $categories,
            'stores' => $stores,
            'sizes' => $sizes,
            'currencies' => $currencies,
            'ledgers' => $ledgers,
        ]);
    }

    public function update(UpdatePreferencesRequest $request)
    {
        $user = $request->user();
        $validated = $request->validated();

        // Merge validated preferences with existing ones
        $currentPreferences = $user->preferences ?? User::DEFAULT_PREFERENCES;
        $newPreferences = array_replace_recursive($currentPreferences, $validated);

        $user->update(['preferences' => $newPreferences]);
        Cache::forget(CacheKey::forUser($request, 'preferences'));
        Cache::forget(CacheKey::forUser($request, 'recordsPerPage'));
        Cache::put('recordsPerPage', $newPreferences['appearance']['records_per_page']);
        return redirect()->back()->with('success', value: __('preferences.preferences_saved'));
    }

    public function resetPreferences(Request $request, ?string $category = null)
    {
        $user = $request->user();
        $user->resetPreferences($category)->save();
        Cache::forget(CacheKey::forUser($request, 'preferences'));
        return redirect()->back()->with('success', __('preferences.preferences_reset'));
    }

    public function updateInstallPlugins(Request $request)
    {
        $user = $request->user();

        $validated = $request->validate([
            'unit_measures' => ['array'],
            'unit_measures.*' => ['string', 'exists:unit_measures,id'],
            'categories' => ['array'],
            'categories.*' => ['string', 'exists:categories,id'],
            'stores' => ['array'],
            'stores.*' => ['string', 'exists:stores,id'],
            'sizes' => ['array'],
            'sizes.*' => ['string', 'exists:sizes,id'],
            'currencies' => ['array'],
            'currencies.*' => ['string', 'exists:currencies,id'],
            'ledgers' => ['array'],
            'ledgers.*' => ['string', 'exists:ledgers,id'],
        ]);

        $unitMeasureIds = collect($validated['unit_measures'] ?? [])->filter()->unique()->values();
        $categoryIds = collect($validated['categories'] ?? [])->filter()->unique()->values();
        $storeIds = collect($validated['stores'] ?? [])->filter()->unique()->values();
        $sizeIds = collect($validated['sizes'] ?? [])->filter()->unique()->values();
        $currencyIds = collect($validated['currencies'] ?? [])->filter()->unique()->values();
        $ledgerIds = collect($validated['ledgers'] ?? [])->filter()->unique()->values();

        DB::transaction(function () use ($unitMeasureIds, $categoryIds, $storeIds, $sizeIds, $currencyIds, $ledgerIds) {
            UnitMeasure::query()->update(['is_active' => false]);
            Category::query()->update(['is_active' => false]);
            Store::query()->update(['is_active' => false]);
            Size::query()->update(['is_active' => false]);
            Currency::query()->update(['is_active' => false]);
            Ledger::query()->whereIn('type', ['customer', 'supplier'])->update(['is_active' => false]);

            if ($unitMeasureIds->isNotEmpty()) UnitMeasure::query()->whereIn('id', $unitMeasureIds)->update(['is_active' => true]);
            if ($categoryIds->isNotEmpty()) Category::query()->whereIn('id', $categoryIds)->update(['is_active' => true]);
            if ($storeIds->isNotEmpty()) Store::query()->whereIn('id', $storeIds)->update(['is_active' => true]);
            if ($sizeIds->isNotEmpty()) Size::query()->whereIn('id', $sizeIds)->update(['is_active' => true]);
            if ($currencyIds->isNotEmpty()) Currency::query()->whereIn('id', $currencyIds)->update(['is_active' => true]);
            if ($ledgerIds->isNotEmpty()) Ledger::query()->whereIn('id', $ledgerIds)->update(['is_active' => true]);
        });

        Cache::forget(CacheKey::forUser($request, 'preferences'));
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'categories'));
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'stores'));
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'sizes'));
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'currencies'));
        Cache::forget(CacheKey::forCompanyBranchLocale($request, 'unit_measures'));

        return redirect()->back()->with('success', __('preferences.preferences_saved'));
    }

    public function exportPreferences(Request $request)
    {
        $user = $request->user();
        $preferences = $user->getAllPreferences();

        return response()->json($preferences)
            ->header('Content-Disposition', 'attachment; filename="preferences.json"')
            ->header('Content-Type', 'application/json');
    }

    public function importPreferences(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:json|max:1024',
        ]);

        $content = file_get_contents($request->file('file')->path());
        $preferences = json_decode($content, true);

        if (!is_array($preferences)) {
            return redirect()->back()->with('error', __('preferences.invalid_preferences_file'));
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

