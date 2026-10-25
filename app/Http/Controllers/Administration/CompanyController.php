<?php

namespace App\Http\Controllers\Administration;

use App\Http\Controllers\Controller;
use App\Http\Requests\Administration\CompanyStoreRequest;
use App\Http\Requests\Administration\CompanyUpdateRequest;
use App\Http\Resources\Administration\CompanyResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Jobs\RefreshCompanyProfileCache;
use App\Models\Account\Account;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Sale\InvoiceFormat;
use App\Services\CompanySetupService;
use App\Services\CompanySettingsService;
use App\Services\DateConversionService;
use App\Support\Preferences\InvoiceThemeOptions;
use App\Support\Settings;
use App\Services\UserAccessService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

/**
 * The companies screen: only ever the companies the signed-in user belongs to.
 *
 * `companies` carries no company_id, so neither the row level security
 * policies nor a company scope bound it — and the policies cannot either,
 * because the super-admin bypass in AuthServiceProvider answers before they
 * run, and super-admins are exactly who use this screen. Without the checks
 * below it listed every company in the database and let any super-admin read,
 * change, delete or restore another customer's company by its id.
 */
class CompanyController extends Controller
{
    public function __construct(private readonly UserAccessService $access)
    {
        $this->authorizeResource(Company::class, 'company');

        // As middleware rather than in each action: it has to run before the
        // form request validates, or another company's id gets a validation
        // answer instead of a 404.
        $this->middleware(function (Request $request, \Closure $next) {
            $company = $request->route('company');

            if ($company instanceof Company) {
                $this->ensureMember($request, $company);
            }

            return $next($request);
        });
    }

    /**
     * The companies page is the picker now (WorkspaceController::companies):
     * the list that lived here showed columns the table no longer has.
     */
    public function index(Request $request)
    {
        return redirect()->route('workspace.companies');
    }

    /**
     * Adding a company beside the ones the user has (phase 5): the same full
     * form as sign-up, posting here instead.
     */
    public function create(Request $request)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        return inertia('Company/Create', [
            'submitRoute' => 'companies.store',
            'defaultSettings' => \App\Support\Settings::forCompany(null),
        ]);
    }

    /**
     * Super-admins only, by decision: a company is a set of books, and who may
     * open one is not a permission to hand out with a role. The new company is
     * provisioned and granted to its creator; their home stays where it was.
     */
    public function store(CompanyStoreRequest $request, CompanySetupService $setup)
    {
        abort_unless($request->user()->isSuperAdmin(), 403);

        $result = $setup->create($request->companyAttributes(), $request->validated('base_currency'), $request->user());

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('companies.index'), 'summary' => $result['summary']], 201);
        }

        return redirect()->route('companies.index')
            ->with('success', __('general.created_successfully', ['resource' => __('general.resource.company')]));
    }

    /**
     * One company, read-only: who it is, how its books are kept, how to reach
     * it, its licence and its branches. Editing is the edit page.
     */
    public function show(Request $request, Company $company)
    {
        $user = $request->user();
        $isActive = $this->isActive($company);
        $dates = app(DateConversionService::class);

        $branches = Branch::query()
            ->withoutGlobalScope('companySpecific')
            ->where('company_id', $company->id)
            ->orderByDesc('is_main')
            ->orderBy('name')
            ->get(['id', 'name', 'location', 'is_main'])
            ->map(fn (Branch $branch) => [
                'id' => (string) $branch->id,
                'name' => (string) $branch->name,
                'location' => $branch->location,
                'is_main' => (bool) $branch->is_main,
            ]);

        return inertia('Company/Show', [
            'company' => [
                ...$this->companyFields($company),
                'currency' => $isActive ? $company->currency?->display_name : null,
                'license_issued_at' => $dates->toDisplay($company->license_issued_at?->toDateString()),
                'license_expires_at' => $dates->toDisplay($company->license_expires_at?->toDateString()),
                'created_at' => $dates->toDisplay($company->created_at?->toDateString()),
            ],
            'isActive' => $isActive,
            'isHome' => (string) $company->id === (string) $user->company_id,
            // Editing reaches the company's books (currency, settings), which
            // are only open while it is the one being worked in.
            'canEdit' => $isActive && $user->can('update', $company),
            'branches' => $branches,
            'canViewBranches' => $isActive && $user->can('branches.view_any'),
            // Phase 6: the settings everyone in the company shares, shown
            // read-only. Account names are the active company's books only.
            'settings' => \App\Support\Settings::forCompany($company),
            'cashAccounts' => $isActive
                ? \App\Models\Account\Account::query()->select('id', 'name')->where('is_active', true)->orderBy('name')->get()
                : [],
        ]);
    }

    public function edit(Request $request, Company $company)
    {
        abort_unless($this->isActive($company), 403, __('messages.errors.company_edit_active_only'));

        $user = $request->user();
        $dates = app(DateConversionService::class);

        return inertia('Company/Edit', [
            'company' => [
                ...$this->companyFields($company),
                'currency_id' => $company->currency_id,
                'license_issued_at' => $dates->toDisplay($company->license_issued_at?->toDateString()),
                'license_expires_at' => $dates->toDisplay($company->license_expires_at?->toDateString()),
            ],
            // This company's own currencies, not the catalog: the base one is
            // picked from what the company already has.
            'companyCurrencies' => CurrencyResource::collection(Currency::query()->orderBy('name')->get())->resolve(),
            'settings' => Settings::forCompany($company),
            'canEditSettings' => $user->can('company_settings.update'),
            'cashAccounts' => Account::select('id', 'name')->where('is_active', true)->orderBy('name')->get(),
            'invoiceThemes' => InvoiceThemeOptions::all(),
            'invoiceFormats' => InvoiceFormat::query()
                ->where('company_id', $company->id)
                ->orderByDesc('is_default')
                ->orderBy('name')
                ->get(),
            'invoiceFormatDefaults' => InvoiceFormat::defaultConfig(),
            'currentInvoiceTheme' => $company->invoice_theme ?: 'format1',
        ]);
    }

    /**
     * Save the edit page: the company's own fields and, for someone allowed to
     * change them, the settings it shares (one request, so a half-saved edit
     * cannot happen).
     */
    public function update(CompanyUpdateRequest $request, Company $company, CompanySettingsService $settings)
    {
        abort_unless($this->isActive($company), 403, __('messages.errors.unauthorized_company_update'));

        $attributes = $request->companyAttributes();
        $attributes['updated_by'] = $request->user()->id;

        // The chosen currency is the company's base currency.
        $currency = Currency::findOrFail($attributes['currency_id']);
        $currency->forceFill(['is_base_currency' => true, 'exchange_rate' => 1.00])->save();

        $oldLogo = $company->logo;
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $attributes['logo'] = $request->file('logo')->store('company-logos', 'public');
        }

        $company->update($attributes);

        if (isset($attributes['logo']) && $oldLogo && $oldLogo !== $company->logo
            && Storage::disk('public')->exists($oldLogo)) {
            Storage::disk('public')->delete($oldLogo);
        }

        if ($request->has('settings') && $request->user()->can('company_settings.update')) {
            $settings->save($company, (array) $request->validated('settings', []));
        }

        Cache::forget('user_'.$request->user()->id);

        // The trade shapes which item fields, sections and tracking defaults
        // every user in the company sees, and that is cached per company.
        if ($company->wasChanged(['business_type', 'currency_id', 'calendar_type'])) {
            RefreshCompanyProfileCache::dispatch($company->id);
        }

        $message = __('general.updated_successfully', ['resource' => __('general.resource.company')]);

        if ($request->wantsJson()) {
            return response()->json(['redirect' => route('companies.show', $company), 'message' => $message]);
        }

        return redirect()->route('companies.show', $company)->with('success', $message);
    }
    public function destroy(Request $request, Company $company)
    {
        $company->delete();

        return response()->noContent();
    }

    public function restore(Request $request, Company $company)
    {
        $company->restore();
        return redirect()->route('companies.index')->with('success', __('general.restored_successfully', ['resource' => __('general.resource.company')]));
    }

    /** Whether this is the company the request is working in. */
    private function isActive(Company $company): bool
    {
        return app()->bound('active_company_id')
            && (string) app('active_company_id') === (string) $company->id;
    }

    /**
     * The fields the show and edit pages share, with enums as their values.
     *
     * @return array<string, mixed>
     */
    private function companyFields(Company $company): array
    {
        return [
            'id' => (string) $company->id,
            'name' => $company->localizedName(),
            'name_en' => $company->name_en,
            'name_fa' => $company->name_fa,
            'name_pa' => $company->name_pa,
            'abbreviation' => $company->abbreviation,
            'logo_url' => $company->logo_url,
            'business_type' => $company->business_type?->value,
            'locale' => $company->locale?->value,
            'calendar_type' => $company->calendar_type?->value,
            'working_style' => $company->working_style?->value,
            'costing_method' => $company->costing_method?->value,
            'phone' => $company->phone,
            'email' => $company->email,
            'website' => $company->website,
            'country' => $company->country,
            'city' => $company->city,
            'address' => $company->address,
            'license_number' => $company->license_number,
            'invoice_description' => $company->invoice_description,
        ];
    }
    /**
     * 404, not 403: another customer's company should not be confirmed to
     * exist by the answer.
     */
    private function ensureMember(Request $request, Company $company): void
    {
        abort_unless($this->access->isMemberOf($request->user(), (string) $company->getKey()), 404);
    }
}
