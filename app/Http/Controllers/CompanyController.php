<?php

namespace App\Http\Controllers;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Http\Requests\Administration\CompanyUpdateRequest;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class CompanyController extends Controller
{
    /**
     * Show the form for creating a new company.
     */
    public function create()
    {
        return inertia('Company/Create');
    }

    /**
     * Store a newly created company in storage.
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name_en' => 'required|string|max:255',
            'name_fa' => 'nullable|string|max:255',
            'name_pa' => 'nullable|string|max:255',
            'abbreviation' => 'nullable|string|max:50',
            'address' => 'nullable|string',
            'phone' => 'nullable|string|max:20',
            'country' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'logo' => 'nullable|string',
            'calendar_type' => 'required|in:' . implode(',', array_column(CalendarType::cases(), 'value')),
            'working_style' => 'required|in:' . implode(',', array_column(WorkingStyle::cases(), 'value')),
            'business_type' => 'required|in:' . implode(',', array_column(BusinessType::cases(), 'value')),
            'locale' => 'required|in:' . implode(',', array_column(Locale::cases(), 'value')),
            'currency_id' => 'required|exists:currencies,id',
            'email' => 'nullable|email|max:255',
            'website' => 'nullable|url|max:255',
            'invoice_description' => 'nullable|string',
        ]);

        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        $company = Company::create($validated);

        // Update the user's company_id
        $user = Auth::user();
        $user->company_id = $company->id;
        $user->save();

        return redirect()->route('dashboard')
            ->with('success', 'Company created successfully.');
    }

    /**
     * Display the company information page.
     */
    public function show()
    {
        $company = Auth::user()->company;

        if (!$company) {
            return redirect()->route('company.create')
                ->with('error', 'No company found. Please create a company first.');
        }


        return inertia('Administration/Companies/Show', [
            'company' => $company
        ]);
    }

    /**
     * Update the company information.
     */
    public function update(CompanyUpdateRequest $request, Company $company)
    {
        // Ensure the user can only update their own company
        if (Auth::user()->company_id !== $company->id) {
            abort(403, 'Unauthorized to update this company.');
        }

        $validated = $request->validated();
        $validated['updated_by'] = Auth::id();

        $company->update($validated);

        return redirect()->back()
            ->with('success', 'Company information updated successfully.');
    }
}
