<?php

namespace App\Http\Controllers;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Http\Requests\Administration\CompanyUpdateRequest;
use App\Http\Requests\Administration\CompanyStoreRequest;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
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
    public function store(CompanyStoreRequest $request)
    {

        $validated = $request->validated();
        
        $validated['created_by'] = Auth::id();
        $validated['updated_by'] = Auth::id();

        // Handle logo upload
        if ($request->hasFile('logo')) {
            $logoPath = $request->file('logo')->store('company-logos', 'public');
            $validated['logo'] = $logoPath;
        }

        $company = Company::create($validated);
        $currency = Currency::find($validated['currency_id']);
        $currency->is_base_currency = true;
        $currency->exchange_rate = 1.00;
        $currency->save();
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
        $company = Auth::user()->company->load('currency');

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
        // return $request->all();
        // Ensure the user can only update their own company
        if (Auth::user()->company_id !== $company->id) {
            abort(403, 'Unauthorized to update this company.');
        }

        $validated = $request->validated();

        // dd($validated);
        $validated['updated_by'] = Auth::id();
        $currency = Currency::find($validated['currency_id']);
        $currency->is_base_currency = true;
        $currency->exchange_rate = 1.00;
        $currency->save();

        $newLogoPath = null;
        if ($request->hasFile('logo') && $request->file('logo')->isValid()) {
            $newLogoPath = $request->file('logo')->store('company-logos', 'public');
        }

        if ($newLogoPath) {
            // Schedule old logo deletion after commit
            $oldLogoPath = $company->logo;

            $validated['logo'] = $newLogoPath;

            $company->update($validated);


            // Delete old file after successful commit
            if ($oldLogoPath && Storage::disk('public')->exists($oldLogoPath)) {
                Storage::disk('public')->delete($oldLogoPath);
            }
        } else {
            // No new file; just update other fields
            $company->update($validated);
        }

        // $company->update($validated);

        return redirect()->back()
            ->with('success', 'Company information updated successfully.');
    }
    public function destroy(Company $company)
    {
        $company->delete();
        return redirect()->route('companies.index')
            ->with('success', 'Company deleted successfully.');
    }
    public function restore(Company $company)
    {
        $company->restore();
        return redirect()->route('companies.index')
            ->with('success', 'Company restored successfully.');
    }
}
