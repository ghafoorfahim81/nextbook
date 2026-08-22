<?php

namespace App\Http\Controllers\Hr;

use App\Enums\EmploymentStatus;
use App\Enums\EmploymentType;
use App\Enums\Gender;
use App\Enums\MaritalStatus;
use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeStoreRequest;
use App\Http\Requests\Hr\EmployeeUpdateRequest;
use App\Http\Resources\Hr\EmployeeListResource;
use App\Http\Resources\Hr\EmployeeResource;
use App\Models\Administration\Country;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Administration\Designation;
use App\Models\Administration\Province;
use App\Models\Hr\Employee;
use App\Models\User;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
use App\Services\DateConversionService;
use App\Services\DeletedRecordService;
use App\Services\Hr\EmployeeLedgerService;
use App\Services\SpreadsheetExportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeController extends Controller
{
    private DateConversionService $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Employee::class, 'employee');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortField = $request->input('sortField', 'code');
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';
        $filters = (array) $request->input('filters', []);

        // Whitelisted: an unchecked sortField goes straight into orderBy.
        $sortableFields = [
            'code' => 'employees.code',
            'full_name' => 'employees.full_name',
            'joining_date' => 'employees.joining_date',
            'employment_status' => 'employees.employment_status',
            'employment_type' => 'employees.employment_type',
            'basic_salary' => 'employees.basic_salary',
            'created_at' => 'employees.created_at',
        ];
        $sortColumn = $sortableFields[$sortField] ?? 'employees.code';

        $employees = Employee::query()
            ->with(['department:id,name', 'designation:id,name', 'manager:id,full_name', 'currency:id,code', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter($filters)
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/Employees/Index', [
            'employees' => EmployeeListResource::collection($employees),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $sortField,
                'sortDirection' => $sortDirection,
                'filters' => $filters,
            ],
        ]);
    }

    public function create()
    {
        return inertia('Hr/Employees/Create', [
            'nextCode' => Employee::nextCode(),
            'options' => $this->formOptions(),
        ]);
    }

    public function store(EmployeeStoreRequest $request, AttachmentService $attachments, ActivityLogService $activityLog)
    {
        $employee = DB::transaction(function () use ($request, $attachments, $activityLog) {
            $validated = $this->normalizeDates($request->validated());

            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store('employees/photos', 'public');
            }

            unset($validated['documents']);

            // The companion ledger is created by EmployeeObserver, inside this
            // same transaction — an employee without one cannot be paid.
            $employee = Employee::create($validated);

            if ($request->hasFile('documents')) {
                $attachments->store($employee, $request->file('documents'));
            }

            $activityLog->logCreate(
                reference: $employee,
                module: 'employee',
                description: "Employee {$employee->code} ({$employee->full_name}) created.",
                newValues: [
                    'code' => $employee->code,
                    'full_name' => $employee->full_name,
                    'department_id' => $employee->department_id,
                    'designation_id' => $employee->designation_id,
                    'employment_type' => $employee->employment_type?->value,
                    'employment_status' => $employee->employment_status?->value,
                    'joining_date' => $employee->joining_date?->toDateString(),
                ],
                metadata: ['ledger_id' => $employee->ledger_id],
            );

            return $employee;
        });

        if ((bool) $request->input('create_and_new')) {
            return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.employee')]));
        }

        return redirect()->route('employees.show', $employee->id)
            ->with('success', __('general.created_successfully', ['resource' => __('hr.employee')]));
    }

    public function show(Request $request, Employee $employee)
    {
        $employee->load([
            'department:id,name', 'designation:id,name', 'manager:id,full_name',
            'currency:id,code', 'country:id,name', 'province:id,name', 'user:id,name',
            'ledger', 'contracts.currency:id,code', 'documents.verifiedBy:id,name',
            'attachments', 'createdBy:id,name', 'updatedBy:id,name',
        ]);

        return inertia('Hr/Employees/Show', [
            'employee' => new EmployeeResource($employee),
        ]);
    }

    public function edit(Request $request, Employee $employee)
    {
        $employee->load(['attachments', 'ledger']);

        return inertia('Hr/Employees/Edit', [
            'employee' => new EmployeeResource($employee),
            'options' => $this->formOptions(),
        ]);
    }

    public function update(EmployeeUpdateRequest $request, Employee $employee, AttachmentService $attachments, ActivityLogService $activityLog)
    {
        DB::transaction(function () use ($request, $employee, $attachments, $activityLog) {
            $before = $employee->only([
                'code', 'full_name', 'department_id', 'designation_id',
                'employment_type', 'employment_status', 'joining_date', 'is_active',
            ]);

            $validated = $this->normalizeDates($request->validated());

            if ($request->hasFile('photo')) {
                $validated['photo'] = $request->file('photo')->store('employees/photos', 'public');
            }

            unset($validated['documents']);

            $employee->update($validated);

            if ($request->hasFile('documents')) {
                $attachments->store($employee, $request->file('documents'));
            }

            $activityLog->logUpdate(
                reference: $employee,
                before: $before,
                after: $employee->only(array_keys($before)),
                module: 'employee',
                description: "Employee {$employee->code} ({$employee->full_name}) updated.",
            );
        });

        return redirect()->route('employees.show', $employee->id)
            ->with('success', __('general.updated_successfully', ['resource' => __('hr.employee')]));
    }

    public function destroy(Request $request, Employee $employee)
    {
        if (! $employee->canBeDeleted()) {
            return redirect()->route('employees.index')->with('error', $employee->getDependencyMessage());
        }

        // The observer cascades the soft delete onto the employee's ledger, and
        // reverses it on restore — which is what makes the Undo toast work.
        $employee->delete();

        return redirect()->route('employees.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('hr.employee')]));
    }

    public function restore(Request $request, Employee $employee)
    {
        $this->authorize('update', $employee);

        $employee->restore();

        return redirect()->route('employees.index')
            ->with('success', __('general.restored_successfully', ['resource' => __('hr.employee')]));
    }

    public function forceDelete(Request $request, Employee $employee, EmployeeLedgerService $ledgers)
    {
        $this->authorize('delete', $employee);

        // Refuse rather than orphan posted vouchers. The check runs before the
        // employee row goes, so nothing is half-removed.
        if (! $ledgers->forceDeleteFor($employee)) {
            return redirect()->route('employees.index')->with('error', __('hr.errors.ledger_has_history'));
        }

        app(DeletedRecordService::class)->forceDelete('employees', (string) $employee->id);

        return redirect()->route('employees.index')
            ->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.employee')]));
    }

    public function exportList(Request $request, SpreadsheetExportService $exporter)
    {
        $this->authorize('viewAny', Employee::class);

        $employees = Employee::query()
            ->with(['department:id,name', 'designation:id,name', 'currency:id,code'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('code')
            ->get();

        $rtl = in_array(app()->getLocale(), ['fa', 'ps'], true);
        $t = fn (string $group, string $key, string $fallback = '') => $exporter->localeTranslation($group, $key, $fallback);
        $dates = $this->dateConversionService;

        return $exporter->download([
            'filename' => 'employees-'.now()->format('Ymd-His').'.xlsx',
            'sheet_name' => $t('hr', 'employees', 'Employees'),
            'title' => $t('hr', 'employees', 'Employees'),
            'company_name' => $this->companyName(),
            'exported_on' => now()->format('Y m d'),
            'rtl' => $rtl,
            'include_row_number' => true,
            'row_number_label' => $t('report', 'columns.no', 'No.'),
            'columns' => [
                ['key' => 'code', 'label' => $t('general', 'code', 'Code'), 'width' => 12],
                ['key' => 'full_name', 'label' => $t('general', 'name', 'Name'), 'width' => 24],
                ['key' => 'father_name', 'label' => $t('hr', 'father_name', 'Father name'), 'width' => 20],
                ['key' => 'department', 'label' => $t('hr', 'department', 'Department'), 'width' => 18],
                ['key' => 'designation', 'label' => $t('hr', 'designation', 'Designation'), 'width' => 18],
                ['key' => 'employment_type', 'label' => $t('hr', 'employment_type', 'Type'), 'width' => 16],
                ['key' => 'employment_status', 'label' => $t('general', 'status', 'Status'), 'width' => 16],
                ['key' => 'joining_date', 'label' => $t('hr', 'joining_date', 'Joining date'), 'width' => 14],
                ['key' => 'phone_number', 'label' => $t('general', 'phone', 'Phone'), 'width' => 16],
                ['key' => 'basic_salary', 'label' => $t('hr', 'basic_salary', 'Basic salary'), 'type' => 'money', 'align' => 'right', 'width' => 16],
                ['key' => 'currency', 'label' => $t('general', 'currency', 'Currency'), 'width' => 10],
            ],
            'rows' => $employees->map(fn (Employee $e) => [
                'code' => $e->code,
                'full_name' => $e->full_name,
                'father_name' => $e->father_name,
                'department' => $e->department?->name,
                'designation' => $e->designation?->name,
                'employment_type' => $e->employment_type?->getLabel(),
                'employment_status' => $e->employment_status?->getLabel(),
                'joining_date' => $dates->toDisplay($e->joining_date),
                'phone_number' => $e->phone_number,
                'basic_salary' => (float) $e->basic_salary,
                'currency' => $e->currency?->code,
            ])->all(),
        ]);
    }

    /**
     * Jalali input to Gregorian storage. Idempotent, so it is safe even when a
     * value has already been converted upstream.
     */
    private function normalizeDates(array $validated): array
    {
        foreach (['date_of_birth', 'joining_date', 'probation_end_date', 'confirmation_date', 'separation_date'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = $this->dateConversionService->toGregorian($validated[$field]);
            }
        }

        return $validated;
    }

    private function formOptions(): array
    {
        return array_merge($this->filterOptions(), [
            'genders' => $this->enumOptions(Gender::cases()),
            'maritalStatuses' => $this->enumOptions(MaritalStatus::cases()),
            'paymentModes' => $this->enumOptions(PaymentMode::cases()),
            'currencies' => Currency::query()->orderBy('code')->get(['id', 'code', 'name']),
            'countries' => Country::query()
                ->orderBy('name_en')
                ->get(['id', 'name_en', 'name_fa'])
                ->map(fn (Country $country) => [
                    'id' => $country->id,
                    'name' => $country->localized_name,
                ]),
            'provinces' => Province::query()
                ->orderBy('name_en')
                ->get(['id', 'country_id', 'name_en', 'name_fa'])
                ->map(fn (Province $province) => [
                    'id' => $province->id,
                    'country_id' => $province->country_id,
                    'name' => $province->localized_name,
                ]),
            // Users who are not already tied to another employee — the
            // employees.user_id unique index would reject them anyway, and
            // offering them only to fail validation is worse than omitting them.
            'users' => User::query()
                ->whereNull('deleted_at')
                ->whereNotIn('id', Employee::query()->whereNotNull('user_id')->pluck('user_id'))
                ->orderBy('name')
                ->get(['id', 'name']),
        ]);
    }

    private function filterOptions(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'designations' => Designation::query()->orderBy('name')->get(['id', 'name']),
            'managers' => Employee::query()->employed()->orderBy('full_name')->get(['id', 'full_name as name']),
            'employmentTypes' => $this->enumOptions(EmploymentType::cases()),
            'employmentStatuses' => $this->enumOptions(EmploymentStatus::cases()),
        ];
    }

    private function enumOptions(array $cases): array
    {
        return array_map(fn ($case) => ['id' => $case->value, 'name' => $case->getLabel()], $cases);
    }

    private function companyName(): ?string
    {
        $company = auth()->user()?->company;

        return match (app()->getLocale()) {
            'fa' => $company?->name_fa ?: $company?->name_en,
            'ps' => $company?->name_pa ?: $company?->name_en,
            default => $company?->name_en,
        };
    }
}
