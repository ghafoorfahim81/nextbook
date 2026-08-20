<?php

namespace App\Http\Controllers\Hr;

use App\Exceptions\Hr\PayrollException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\SalaryPaymentStoreRequest;
use App\Http\Resources\Hr\SalaryPaymentResource;
use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Hr\Employee;
use App\Models\Hr\SalaryPayment;
use App\Services\ActivityLogService;
use App\Services\Hr\SalaryDisbursementService;
use Illuminate\Http\Request;

/**
 * Paying employees what the payroll accrued.
 *
 * All the work is in SalaryDisbursementService, which routes through
 * SettlementService rather than posting its own voucher — so partial payment,
 * FX realisation and overpayment handling behave exactly as they do for a
 * supplier payment.
 */
class SalaryPaymentController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(SalaryPayment::class, 'salary_payment');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $payments = SalaryPayment::query()
            ->with([
                'employee:id,full_name,code,branch_id',
                'payroll:id,number,period_label,branch_id',
                'currency:id,code',
                'bankAccount:id,name',
                'createdBy:id,name',
            ])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('date', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/SalaryPayments/Index', [
            'salaryPayments' => SalaryPaymentResource::collection($payments),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'date',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function create()
    {
        return inertia('Hr/SalaryPayments/Create', [
            'filterOptions' => $this->filterOptions(),
            'nextNumber' => SalaryPayment::nextNumber(),
        ]);
    }

    public function show(SalaryPayment $salaryPayment)
    {
        $salaryPayment->load([
            'employee:id,full_name,code,branch_id',
            'payroll:id,number,period_label,branch_id',
            'currency:id,code',
            'bankAccount:id,name',
            'lines.payrollLine.payroll:id,number,period_label,branch_id',
            'lines.employee:id,full_name,code,branch_id',
            'lines.currency:id,code',
            'createdBy:id,name',
        ]);

        return inertia('Hr/SalaryPayments/Show', [
            'salaryPayment' => new SalaryPaymentResource($salaryPayment),
        ]);
    }

    /**
     * The payslips an employee is still owed, for the payment form.
     *
     * Read from the general ledger rather than from payroll_lines.paid_amount:
     * the ledger is what the money actually did, and the column is a cache of
     * it. If they ever disagree, the form should offer what is genuinely open.
     */
    public function openPayslips(Request $request, SalaryDisbursementService $disbursement)
    {
        $this->authorize('create', SalaryPayment::class);

        $validated = $request->validate([
            'employee_id' => ['required', 'string', 'exists:employees,id'],
            'currency_id' => ['nullable', 'string', 'exists:currencies,id'],
        ]);

        $employee = Employee::query()->findOrFail($validated['employee_id']);

        return response()->json([
            'open_items' => $disbursement->openPayslips(
                $employee,
                $validated['currency_id'] ?? null
            ),
        ]);
    }

    public function store(SalaryPaymentStoreRequest $request, SalaryDisbursementService $disbursement, ActivityLogService $activityLog)
    {
        $validated = $request->validated();

        try {
            $payment = $disbursement->pay(
                $validated,
                $validated['allocations'] ?? []
            );
        } catch (PayrollException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        } catch (\App\Exceptions\Accounting\SettlementException $exception) {
            return redirect()->back()->with('error', $exception->getMessage());
        }

        $activityLog->logCreate(
            reference: $payment,
            module: 'salary_payment',
            description: "Salary payment #{$payment->number} created.",
            newValues: [
                'number' => $payment->number,
                'employee_id' => $payment->employee_id,
                'amount' => (float) $payment->amount,
            ],
        );

        return redirect()->route('salary-payments.show', $payment)
            ->with('success', __('general.created_successfully', [
                'resource' => __('hr.salary_payment'),
            ]));
    }

    /**
     * Void a payment: its voucher and settlements go with it, and the payslip
     * goes back on the open list.
     */
    public function destroy(Request $request, SalaryPayment $salaryPayment, SalaryDisbursementService $disbursement, ActivityLogService $activityLog)
    {
        $oldValues = [
            'number' => $salaryPayment->number,
            'employee_id' => $salaryPayment->employee_id,
            'amount' => (float) $salaryPayment->amount,
        ];

        $disbursement->void($salaryPayment);

        $activityLog->logDelete(
            reference: $salaryPayment,
            module: 'salary_payment',
            description: "Salary payment #{$salaryPayment->number} voided.",
            oldValues: $oldValues,
        );

        return redirect()->route('salary-payments.index')
            ->with('success', __('general.deleted_successfully', [
                'resource' => __('hr.salary_payment'),
            ]));
    }

    public function restore(Request $request, SalaryPayment $salaryPayment, SalaryDisbursementService $disbursement)
    {
        $this->authorize('update', $salaryPayment);

        $disbursement->restore($salaryPayment);

        return redirect()->back()->with('success', __('general.restored_successfully', [
            'resource' => __('hr.salary_payment'),
        ]));
    }

    private function filterOptions(): array
    {
        return [
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'bankAccounts' => Account::query()
                ->whereHas('accountType', fn ($query) => $query->where('slug', 'cash-or-bank'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ];
    }
}
