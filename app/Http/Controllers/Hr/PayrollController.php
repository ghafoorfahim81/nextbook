<?php

namespace App\Http\Controllers\Hr;

use App\Enums\EmploymentType;
use App\Enums\PayFrequency;
use App\Enums\PayrollStatus;
use App\Exceptions\Hr\PayrollException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\PayrollStoreRequest;
use App\Http\Requests\Hr\PayrollUpdateRequest;
use App\Http\Resources\Hr\PayrollResource;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Hr\Payroll;
use App\Services\ActivityLogService;
use App\Services\DateConversionService;
use App\Services\Hr\PayrollCalculationService;
use App\Services\Hr\PayrollPostingService;
use Illuminate\Http\Request;

/**
 * Payroll runs.
 *
 * Every state change goes through PayrollPostingService::transitionTo(), so
 * the buttons the UI offers and the transitions the system permits come from
 * the same place — PayrollStatus::allowedTransitions().
 */
class PayrollController extends Controller
{
    public function __construct(
        private readonly DateConversionService $dateConversionService,
    ) {
        $this->authorizeResource(Payroll::class, 'payroll');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $payrolls = Payroll::query()
            ->with(['currency:id,code', 'department:id,name,branch_id', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('period_end', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/Payrolls/Index', [
            'payrolls' => PayrollResource::collection($payrolls),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'period_end',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function create()
    {
        return inertia('Hr/Payrolls/Create', [
            'filterOptions' => $this->filterOptions(),
            'nextNumber' => Payroll::nextNumber(),
        ]);
    }

    public function show(Payroll $payroll)
    {
        $payroll->load([
            'currency:id,code',
            'department:id,name,branch_id',
            'approver:id,name',
            'poster:id,name',
            'createdBy:id,name',
            'lines' => fn ($query) => $query->orderBy('created_at'),
            'lines.employee:id,full_name,code,branch_id',
            'lines.currency:id,code',
            'lines.components',
            'lines.taxBracketSet:id,name,branch_id',
        ]);

        return inertia('Hr/Payrolls/Show', [
            'payroll' => new PayrollResource($payroll),
        ]);
    }

    public function edit(Payroll $payroll)
    {
        if ($payroll->isPosted()) {
            return redirect()->route('payrolls.show', $payroll)
                ->with('error', __('hr.posted_payroll_is_immutable'));
        }

        return inertia('Hr/Payrolls/Edit', [
            'payroll' => new PayrollResource($payroll),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function store(PayrollStoreRequest $request)
    {
        $data = $request->validated();

        $payroll = Payroll::create($data + [
            'number' => (string) Payroll::nextNumber(),
            'status' => PayrollStatus::Draft->value,
            // Denormalised so the list can be searched by Jalali month without
            // converting every row.
            'period_label' => $data['period_label']
                ?? $this->jalaliLabel($data['period_end']),
        ]);

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', __('general.created_successfully', ['resource' => __('hr.payroll')]));
    }

    public function update(PayrollUpdateRequest $request, Payroll $payroll)
    {
        if ($payroll->isPosted()) {
            return redirect()->back()->with('error', __('hr.posted_payroll_is_immutable'));
        }

        $data = $request->validated();

        $payroll->update($data + [
            'period_label' => $data['period_label'] ?? $this->jalaliLabel($data['period_end']),
        ]);

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', __('general.updated_successfully', ['resource' => __('hr.payroll')]));
    }

    /**
     * Build (or rebuild) the payslips.
     *
     * Safe to run repeatedly while the run is draft or calculated — it force-
     * deletes and rebuilds the lines, so correcting attendance and calculating
     * again is the normal workflow rather than an edge case.
     */
    public function calculate(Request $request, Payroll $payroll, PayrollCalculationService $calculator)
    {
        $this->authorize('update', $payroll);

        try {
            $calculator->calculate($payroll);
        } catch (PayrollException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', __('hr.payroll_calculated'));
    }

    /**
     * Move the run along its lifecycle.
     *
     * One endpoint rather than five, because the state machine already knows
     * which moves are legal — duplicating that knowledge in route names would
     * let the two drift.
     */
    public function transition(
        Request $request,
        Payroll $payroll,
        PayrollPostingService $posting,
        ActivityLogService $activityLog
    ) {
        $validated = $request->validate([
            'status' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $target = PayrollStatus::tryFrom($validated['status']);

        if (! $target) {
            return redirect()->back()->with('error', __('hr.unknown_payroll_status'));
        }

        // Approving, posting and reversing are separate grants from editing.
        $ability = match ($target) {
            PayrollStatus::Approved, PayrollStatus::Posted => 'approve',
            PayrollStatus::Cancelled, PayrollStatus::Reversed => 'approve',
            default => 'update',
        };

        $this->authorize($ability, $payroll);

        try {
            $payroll = $posting->transitionTo($payroll, $target, $validated['reason'] ?? null);
        } catch (PayrollException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        // logAction, not logUpdate: this records a state change, and logUpdate
        // takes a before/after diff rather than old/new value arrays — passing
        // those named arguments was a fatal TypeError.
        $activityLog->logAction(
            eventType: $target->value,
            reference: $payroll,
            module: 'payroll',
            description: "Payroll #{$payroll->number} moved to {$target->value}.",
            newValues: ['status' => $target->value, 'reason' => $validated['reason'] ?? null],
        );

        return redirect()->route('payrolls.show', $payroll)
            ->with('success', __('hr.payroll_status_updated'));
    }

    /**
     * One payslip, ready to print.
     */
    public function payslip(Request $request, Payroll $payroll, string $line)
    {
        $this->authorize('view', $payroll);

        $payslip = $payroll->lines()
            ->with(['employee', 'currency:id,code', 'components', 'taxBracketSet:id,name,branch_id'])
            ->findOrFail($line);

        $payroll->load(['currency:id,code', 'department:id,name,branch_id']);

        return inertia('Hr/Payrolls/Payslip', [
            'payroll' => new PayrollResource($payroll),
            'payslip' => new \App\Http\Resources\Hr\PayrollLineResource($payslip),
        ]);
    }

    public function destroy(Request $request, Payroll $payroll)
    {
        // A posted run has moved money. Reversal is the way out of it, and it
        // leaves the GL entries visible; deleting would not.
        if ($payroll->isPosted()) {
            return redirect()->back()->with('error', __('hr.posted_payroll_must_be_reversed'));
        }

        $payroll->delete();

        return redirect()->route('payrolls.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('hr.payroll')]));
    }

    public function restore(Request $request, Payroll $payroll)
    {
        $this->authorize('update', $payroll);
        $payroll->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.payroll'),
        ]));
    }

    /**
     * The Jalali month a period ends in, as `1405-05`.
     *
     * Derived from the END date: a period running 20 Asad to 20 Sunbula is
     * Sunbula's payroll, which is what people call it.
     */
    private function jalaliLabel(?string $periodEnd): ?string
    {
        if (! $periodEnd) {
            return null;
        }

        $display = $this->dateConversionService->toDisplay($periodEnd);

        if (! $display) {
            return null;
        }

        $parts = explode('-', $display);

        return count($parts) >= 2 ? $parts[0].'-'.$parts[1] : null;
    }

    private function filterOptions(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'payFrequencies' => array_map(
                fn (PayFrequency $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                PayFrequency::cases()
            ),
            'employmentTypes' => array_map(
                fn (EmploymentType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                EmploymentType::cases()
            ),
            'statuses' => array_map(
                fn (PayrollStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                PayrollStatus::cases()
            ),
        ];
    }
}
