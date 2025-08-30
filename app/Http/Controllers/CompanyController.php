<?php

namespace App\Http\Controllers;

use App\Enums\BusinessType;
use App\Enums\CalendarType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Models\Administration\Company;
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
}
