<?php

namespace App\Http\Controllers;

use App\Support\CompanyContext;
use App\Support\Settings;
use App\Http\Requests\Administration\CompanyUpdateRequest;
use App\Http\Requests\Administration\CompanyStoreRequest;
use App\Http\Resources\Administration\CurrencyResource;
use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\CostingMethod;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Role;
use App\Models\Sale\InvoiceFormat;
use App\Services\CompanySetupService;
use App\Services\CompanySettingsService;
use App\Support\Inertia\CacheForget;
use App\Support\Preferences\InvoiceThemeOptions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;

class CompanyController extends Controller
{
    /**
     * Show the form for creating a new company.
     */
    public function create()
    {
        return inertia('Company/Create', [
            'defaultSettings' => Settings::forCompany(null),
            'moduleDefaults' => \App\Support\Modules::defaultsByBusinessType(),
        ]);
    }

    /**
     * Store a newly created company in storage.
     */
    /**
     * Sign-up: a new user founds their company.
     *
     * Only for a user who has none yet. Adding a company beside an existing one
     * is a super-admin's job on the companies screen (Administration\
     * CompanyController), which grants it rather than making it home.
     */
    public function store(CompanyStoreRequest $request, CompanySetupService $setup)
    {
        $user = Auth::user();

        abort_if((bool) $user->company_id, 403);

        $result = $setup->create($request->companyAttributes(), $request->validated('base_currency'), $user);

        // CompanySetupService made the founder super-admin of this company.
        CacheForget::authForUserId($user->id);

        // The set-up screen posts for JSON so it can show what was created
        // before moving on; a plain form post just redirects.
        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('dashboard'), 'summary' => $result['summary']], 201);
        }

        return redirect()->route('dashboard')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.company')]));
    }

    /**
     * The old /company address: the active company's page now lives under the
     * companies resource (Administration\CompanyController::show).
     */
    public function show()
    {
        $company = CompanyContext::company();

        if (! $company) {
            return redirect()->route('company.create')
                ->with('error', __('general.no_company_found_create_first'));
        }

        return redirect()->route('companies.show', $company);
    }
    /**
     * Save the active company's settings (phase 6) — the keys listed as
     * company_keys in config/preferences.php, the same for everyone in it.
     */
    public function updateSettings(Request $request)
    {
        abort_unless($request->user()->can('company_settings.update'), 403, __('preferences.company_settings_forbidden'));

        $company = CompanyContext::company();
        abort_unless($company, 404);

        $validated = $request->validate(
            \App\Http\Requests\Preferences\UpdatePreferencesRequest::companyRules()
        );

        app(CompanySettingsService::class)->save($company, $validated);

        return redirect()->back()->with('success', __('preferences.preferences_saved'));
    }

    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->route('companies.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('general.resource.company')]));
    }
    public function restore(Company $company)
    {
        // The route takes deleted companies, and `companies` has no company
        // scope or policy that would keep this to the user's own (see
        // Administration\CompanyController). 404 so another customer's
        // company is not confirmed to exist.
        abort_unless(
            app(\App\Services\UserAccessService::class)->isMemberOf(Auth::user(), (string) $company->getKey()),
            404
        );

        $company->restore();
        return redirect()->route('companies.index')
            ->with('success', __('general.restored_successfully', ['resource' => __('general.resource.company')]));
    }
}
