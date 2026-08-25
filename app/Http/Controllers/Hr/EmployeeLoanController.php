<?php

namespace App\Http\Controllers\Hr;

use App\Enums\LoanStatus;
use App\Enums\LoanType;
use App\Exceptions\Hr\PayrollException;
use App\Http\Controllers\Concerns\ProvidesEmployeeOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeLoanStoreRequest;
use App\Http\Requests\Hr\EmployeeLoanUpdateRequest;
use App\Http\Resources\Hr\EmployeeLoanResource;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Hr\EmployeeLoan;
use App\Services\ActivityLogService;
use App\Services\DateConversionService;
use App\Services\Hr\EmployeeLoanService;
use Illuminate\Http\Request;

class EmployeeLoanController extends Controller
{
    use ProvidesEmployeeOptions;

    public function __construct(
        private readonly DateConversionService $dateConversionService,
    ) {
        $this->authorizeResource(EmployeeLoan::class, 'employee_loan');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $loans = EmployeeLoan::query()
            ->with(['employee:id,full_name,code,branch_id', 'currency:id,code', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('issue_date', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/EmployeeLoans/Index', [
            'employeeLoans' => EmployeeLoanResource::collection($loans),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'issue_date',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function create()
    {
        return inertia('Hr/EmployeeLoans/Create', [
            'filterOptions' => $this->filterOptions(),
            'nextNumber' => EmployeeLoan::nextNumber(),
        ]);
    }

    public function show(EmployeeLoan $employeeLoan)
    {
        $employeeLoan->load([
            'employee:id,full_name,code,branch_id',
            'currency:id,code',
            'repayments' => fn ($query) => $query->orderBy('date'),
            'createdBy:id,name',
        ]);

        return inertia('Hr/EmployeeLoans/Show', [
            'employeeLoan' => new EmployeeLoanResource($employeeLoan),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function edit(EmployeeLoan $employeeLoan)
    {
        if ($employeeLoan->statusEnum()->isDisbursed()) {
            return redirect()->route('employee-loans.show', $employeeLoan)
                ->with('error', __('hr.disbursed_loan_is_immutable'));
        }

        $employeeLoan->load(['employee:id,full_name,code,branch_id', 'currency:id,code']);

        return inertia('Hr/EmployeeLoans/Edit', [
            'employeeLoan' => new EmployeeLoanResource($employeeLoan),
            'filterOptions' => $this->filterOptions(),
        ]);
    }

    public function store(EmployeeLoanStoreRequest $request)
    {
        $loan = EmployeeLoan::create($request->validated() + [
            'number' => (string) EmployeeLoan::nextNumber(),
            'status' => LoanStatus::Draft->value,
        ]);

        return redirect()->route('employee-loans.show', $loan)
            ->with('success', __('general.created_successfully', ['resource' => __('hr.employee_loan')]));
    }

    public function update(EmployeeLoanUpdateRequest $request, EmployeeLoan $employeeLoan)
    {
        // Once the money has gone out, the principal and schedule are facts
        // rather than intentions. Correcting them would silently restate a
        // balance the GL already carries.
        if ($employeeLoan->statusEnum()->isDisbursed()) {
            return redirect()->back()->with('error', __('hr.disbursed_loan_is_immutable'));
        }

        $employeeLoan->update($request->validated());

        return redirect()->route('employee-loans.show', $employeeLoan)
            ->with('success', __('general.updated_successfully', ['resource' => __('hr.employee_loan')]));
    }

    public function submit(Request $request, EmployeeLoan $employeeLoan, EmployeeLoanService $loans)
    {
        $this->authorize('update', $employeeLoan);

        return $this->run(
            fn () => $loans->submit($employeeLoan),
            $employeeLoan,
            __('hr.loan_submitted')
        );
    }

    public function approve(Request $request, EmployeeLoan $employeeLoan, EmployeeLoanService $loans, ActivityLogService $activityLog)
    {
        $this->authorize('approve', $employeeLoan);

        return $this->run(
            function () use ($loans, $employeeLoan, $activityLog) {
                $loan = $loans->approve($employeeLoan);

                // logAction, not logUpdate: this records a state change, and
                // logUpdate takes a before/after diff rather than old/new value
                // arrays — passing those named arguments was a fatal TypeError.
                $activityLog->logAction(
                    eventType: 'approved',
                    reference: $loan,
                    module: 'employee_loan',
                    description: "Loan #{$loan->number} approved.",
                    newValues: ['status' => $loan->statusEnum()->value],
                );

                return $loan;
            },
            $employeeLoan,
            __('hr.loan_approved')
        );
    }

    public function reject(Request $request, EmployeeLoan $employeeLoan, EmployeeLoanService $loans)
    {
        $this->authorize('reject', $employeeLoan);

        $validated = $request->validate([
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        return $this->run(
            fn () => $loans->reject($employeeLoan, $validated['reason'] ?? null),
            $employeeLoan,
            __('hr.loan_rejected')
        );
    }

    /**
     * Hand the money over and post it.
     *
     * Separate from approve() because in practice the person who authorises a
     * staff loan is not the person who opens the safe.
     */
    public function disburse(Request $request, EmployeeLoan $employeeLoan, EmployeeLoanService $loans)
    {
        $this->authorize('approve', $employeeLoan);

        $validated = $request->validate([
            'bank_account_id' => ['nullable', 'string', 'exists:accounts,id'],
        ]);

        return $this->run(
            fn () => $loans->disburse($employeeLoan, $validated['bank_account_id'] ?? null),
            $employeeLoan,
            __('hr.loan_disbursed')
        );
    }

    public function repay(Request $request, EmployeeLoan $employeeLoan, EmployeeLoanService $loans)
    {
        $this->authorize('update', $employeeLoan);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'gt:0'],
            'bank_account_id' => ['required', 'string', 'exists:accounts,id'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
            'rate' => ['nullable', 'numeric', 'gt:0'],
            'remark' => ['nullable', 'string'],
        ]);

        $validated['date'] = $this->dateConversionService->toGregorian($validated['date']);

        return $this->run(
            fn () => $loans->repayInCash($employeeLoan, $validated),
            $employeeLoan,
            __('hr.loan_repayment_recorded')
        );
    }

    public function writeOff(Request $request, EmployeeLoan $employeeLoan, EmployeeLoanService $loans)
    {
        $this->authorize('approve', $employeeLoan);

        $validated = $request->validate([
            'date' => ['required', 'date'],
            'reason' => ['nullable', 'string', 'max:500'],
        ]);

        $date = $this->dateConversionService->toGregorian($validated['date']);

        return $this->run(
            fn () => $loans->writeOff($employeeLoan, $date, $validated['reason'] ?? null),
            $employeeLoan,
            __('hr.loan_written_off')
        );
    }

    public function destroy(Request $request, EmployeeLoan $employeeLoan)
    {
        if ($employeeLoan->statusEnum()->isDisbursed()) {
            return redirect()->back()->with('error', __('hr.disbursed_loan_cannot_be_deleted'));
        }

        $employeeLoan->delete();

        return redirect()->route('employee-loans.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('hr.employee_loan')]));
    }

    public function restore(Request $request, EmployeeLoan $employeeLoan)
    {
        $this->authorize('update', $employeeLoan);
        $employeeLoan->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.employee_loan'),
        ]));
    }

    /**
     * Run a service call, turning its refusal into a flash message rather than
     * an exception page — these are states the system declines, not crashes.
     */
    private function run(callable $action, EmployeeLoan $loan, string $message)
    {
        try {
            $action();
        } catch (PayrollException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        return redirect()->route('employee-loans.show', $loan)->with('success', $message);
    }

    private function filterOptions(): array
    {
        return [
            'loanTypes' => array_map(
                fn (LoanType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                LoanType::cases()
            ),
            'statuses' => array_map(
                fn (LoanStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                LoanStatus::cases()
            ),
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'bankAccounts' => Account::query()
                ->whereHas('accountType', fn ($query) => $query->where('slug', 'cash-or-bank'))
                ->orderBy('name')
                ->get(['id', 'name']),
            'employees' => $this->employeeOptions(),
        ];
    }
}
