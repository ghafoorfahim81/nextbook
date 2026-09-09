<?php

namespace App\Http\Controllers\Accounting;

use App\Http\Controllers\Controller;
use App\Models\Accounting\FinancialPeriod;
use App\Models\Accounting\FiscalYear;
use App\Services\ActivityLogService;
use App\Services\Accounting\FiscalYearService;
use App\Services\DateConversionService;
use App\Support\BranchContext;
use Illuminate\Http\Request;

/**
 * Financial years and the months inside them.
 *
 * Deliberately not a resource controller: a financial year is not created by
 * filling in a form, it is GENERATED from the company's fiscal-year start so
 * that the bounds the guard enforces and the bounds the screen shows can never
 * be two different things. What a user does here is close and reopen.
 */
class FiscalYearController extends Controller
{
    public function __construct(
        private readonly FiscalYearService $fiscalYears,
        private readonly DateConversionService $dates,
    ) {
    }

    public function index(Request $request)
    {
        $this->authorize('viewAny', FiscalYear::class);

        $years = FiscalYear::query()
            ->with(['periods', 'closedBy:id,name'])
            ->orderByDesc('start_date')
            ->get()
            ->map(fn (FiscalYear $year) => [
                'id' => $year->id,
                'name' => $year->name,
                'start_date' => $year->start_date?->toDateString(),
                'end_date' => $year->end_date?->toDateString(),
                'start_date_display' => $year->start_date ? $this->dates->toDisplay($year->start_date) : null,
                'end_date_display' => $year->end_date ? $this->dates->toDisplay($year->end_date) : null,
                'status' => $year->status->value,
                'closed_at' => $year->closed_at?->toDateTimeString(),
                'closed_by' => $year->closedBy?->name,
                'periods' => $year->periods->map(fn (FinancialPeriod $period) => [
                    'id' => $period->id,
                    'name' => $period->name,
                    'start_date_display' => $period->start_date ? $this->dates->toDisplay($period->start_date) : null,
                    'end_date_display' => $period->end_date ? $this->dates->toDisplay($period->end_date) : null,
                    'status' => $period->status->value,
                ])->values(),
            ])
            ->values();

        return inertia('Accounting/FiscalYears/Index', [
            'fiscalYears' => $years,
            'can' => [
                'create' => $request->user()?->can('create', FiscalYear::class),
                'close' => $request->user()?->can('financial_periods.close'),
                'reopen' => $request->user()?->can('financial_periods.reopen'),
            ],
        ]);
    }

    /**
     * Generate the year containing a date — normally the one after the last.
     */
    public function store(Request $request, ActivityLogService $activityLog)
    {
        $this->authorize('create', FiscalYear::class);

        $validated = $request->validate([
            'date' => ['required', 'date'],
        ]);

        $date = $this->dates->toGregorian($validated['date']);
        $branchId = BranchContext::branchId();

        if ($this->fiscalYears->yearFor($date, $branchId)) {
            return back()->withErrors([
                'date' => __('general.fiscal_year_already_exists'),
            ]);
        }

        $year = $this->fiscalYears->generate($date, $branchId);

        $activityLog->logCreate(
            reference: $year,
            module: 'financial_period',
            description: "Financial year {$year->name} created.",
            newValues: [
                'name' => $year->name,
                'start_date' => $year->start_date?->toDateString(),
                'end_date' => $year->end_date?->toDateString(),
            ],
            metadata: ['action' => 'fiscal_year_generate'],
        );

        return back()->with('success', __('general.created_successfully', [
            'resource' => __('general.fiscal_year'),
        ]));
    }

    public function closeYear(FiscalYear $fiscalYear, ActivityLogService $activityLog)
    {
        $this->authorize('close', $fiscalYear);

        $this->fiscalYears->closeYear($fiscalYear);

        $activityLog->logAction(
            eventType: 'closed',
            reference: $fiscalYear,
            module: 'financial_period',
            description: "Financial year {$fiscalYear->name} closed.",
            metadata: ['action' => 'fiscal_year_close'],
        );

        return back()->with('success', __('general.fiscal_year_closed_successfully'));
    }

    public function reopenYear(FiscalYear $fiscalYear, ActivityLogService $activityLog)
    {
        $this->authorize('reopen', $fiscalYear);

        $this->fiscalYears->reopenYear($fiscalYear);

        $activityLog->logAction(
            eventType: 'reopened',
            reference: $fiscalYear,
            module: 'financial_period',
            description: "Financial year {$fiscalYear->name} reopened.",
            metadata: ['action' => 'fiscal_year_reopen'],
        );

        return back()->with('success', __('general.fiscal_year_reopened_successfully'));
    }

    public function closePeriod(FinancialPeriod $financialPeriod, ActivityLogService $activityLog)
    {
        $this->authorize('close', $financialPeriod->fiscalYear ?? FiscalYear::class);

        $this->fiscalYears->closePeriod($financialPeriod);

        $activityLog->logAction(
            eventType: 'closed',
            reference: $financialPeriod,
            module: 'financial_period',
            description: "Financial period {$financialPeriod->name} closed.",
            metadata: ['action' => 'financial_period_close'],
        );

        return back()->with('success', __('general.period_closed_successfully'));
    }

    public function reopenPeriod(FinancialPeriod $financialPeriod, ActivityLogService $activityLog)
    {
        $this->authorize('reopen', $financialPeriod->fiscalYear ?? FiscalYear::class);

        // Throws ClosedPeriodException when the year around it is shut, which
        // the accounting handler renders back onto the page.
        $this->fiscalYears->reopenPeriod($financialPeriod);

        $activityLog->logAction(
            eventType: 'reopened',
            reference: $financialPeriod,
            module: 'financial_period',
            description: "Financial period {$financialPeriod->name} reopened.",
            metadata: ['action' => 'financial_period_reopen'],
        );

        return back()->with('success', __('general.period_reopened_successfully'));
    }
}
