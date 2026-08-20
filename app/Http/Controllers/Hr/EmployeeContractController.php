<?php

namespace App\Http\Controllers\Hr;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\EmployeeContractStoreRequest;
use App\Http\Requests\Hr\EmployeeContractUpdateRequest;
use App\Http\Resources\Hr\EmployeeContractResource;
use App\Models\Hr\EmployeeContract;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
use App\Services\DateConversionService;
use App\Services\DeletedRecordService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EmployeeContractController extends Controller
{
    private DateConversionService $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(EmployeeContract::class, 'employee_contract');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortableFields = [
            'contract_number' => 'contract_number',
            'start_date' => 'start_date',
            'end_date' => 'end_date',
            'status' => 'status',
        ];
        $sortColumn = $sortableFields[$request->input('sortField', 'end_date')] ?? 'end_date';

        $contracts = EmployeeContract::query()
            ->with(['employee:id,full_name,code', 'currency:id,code', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/EmployeeContracts/Index', [
            'contracts' => EmployeeContractResource::collection($contracts),
            'filterOptions' => [
                'contractTypes' => $this->enumOptions(ContractType::cases()),
                'statuses' => $this->enumOptions(ContractStatus::cases()),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $request->input('sortField', 'end_date'),
                'sortDirection' => $sortDirection,
                'filters' => (array) $request->input('filters', []),
            ],
        ]);
    }

    public function store(EmployeeContractStoreRequest $request, AttachmentService $attachments, ActivityLogService $activityLog)
    {
        DB::transaction(function () use ($request, $attachments, $activityLog) {
            $validated = $this->normalizeDates($request->validated());
            unset($validated['documents']);

            $contract = EmployeeContract::create($validated);

            $this->demoteOtherCurrentContracts($contract);

            if ($request->hasFile('documents')) {
                $attachments->store($contract, $request->file('documents'));
            }

            $activityLog->logCreate(
                reference: $contract,
                module: 'employee_contract',
                description: "Contract {$contract->contract_number} created.",
                newValues: $contract->only(['contract_number', 'employee_id', 'start_date', 'end_date', 'status']),
            );
        });

        return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.contract')]));
    }

    public function update(EmployeeContractUpdateRequest $request, EmployeeContract $employeeContract, AttachmentService $attachments, ActivityLogService $activityLog)
    {
        DB::transaction(function () use ($request, $employeeContract, $attachments, $activityLog) {
            $before = $employeeContract->only(['contract_number', 'start_date', 'end_date', 'status', 'is_current']);

            $validated = $this->normalizeDates($request->validated());
            unset($validated['documents']);

            $employeeContract->update($validated);

            $this->demoteOtherCurrentContracts($employeeContract);

            if ($request->hasFile('documents')) {
                $attachments->store($employeeContract, $request->file('documents'));
            }

            $activityLog->logUpdate(
                reference: $employeeContract,
                before: $before,
                after: $employeeContract->only(array_keys($before)),
                module: 'employee_contract',
                description: "Contract {$employeeContract->contract_number} updated.",
            );
        });

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('hr.contract')]));
    }

    public function destroy(Request $request, EmployeeContract $employeeContract)
    {
        $employeeContract->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.contract')]));
    }

    public function restore(Request $request, EmployeeContract $employeeContract)
    {
        $this->authorize('update', $employeeContract);

        $employeeContract->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.contract')]));
    }

    public function forceDelete(Request $request, EmployeeContract $employeeContract)
    {
        $this->authorize('delete', $employeeContract);

        app(DeletedRecordService::class)->forceDelete('employee_contracts', (string) $employeeContract->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.contract')]));
    }

    /**
     * Exactly one contract per employee may be the current one.
     *
     * Enforced here rather than with a partial unique index because marking a
     * new contract current is the normal way to supersede the old one — the
     * user should not have to unset the previous one first.
     */
    private function demoteOtherCurrentContracts(EmployeeContract $contract): void
    {
        if (! $contract->is_current) {
            return;
        }

        EmployeeContract::query()
            ->where('employee_id', $contract->employee_id)
            ->where('id', '!=', $contract->id)
            ->where('is_current', true)
            ->update(['is_current' => false]);
    }

    private function normalizeDates(array $validated): array
    {
        foreach (['start_date', 'end_date', 'terminated_on'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = $this->dateConversionService->toGregorian($validated[$field]);
            }
        }

        return $validated;
    }

    private function enumOptions(array $cases): array
    {
        return array_map(fn ($case) => ['id' => $case->value, 'name' => $case->getLabel()], $cases);
    }
}
