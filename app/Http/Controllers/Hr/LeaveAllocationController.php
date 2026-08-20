<?php

namespace App\Http\Controllers\Hr;

use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\LeaveAllocationStoreRequest;
use App\Http\Requests\Hr\LeaveAllocationUpdateRequest;
use App\Http\Resources\Hr\LeaveAllocationResource;
use App\Models\Hr\LeaveAllocation;
use App\Models\Hr\LeaveType;
use App\Services\DateConversionService;
use App\Services\DeletedRecordService;
use App\Services\Hr\LeaveBalanceService;
use App\Support\BranchContext;
use Illuminate\Http\Request;

class LeaveAllocationController extends Controller
{
    private DateConversionService $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(LeaveAllocation::class, 'leave_allocation');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request, LeaveBalanceService $balances)
    {
        $perPage = $request->input('perPage', recordsPerPage());

        $allocations = LeaveAllocation::query()
            ->with(['employee:id,full_name,code', 'leaveType:id,name', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderByDesc('period_start')
            ->paginate($perPage)
            ->withQueryString();

        // Balances resolved in ONE query for the whole page rather than per row,
        // which is the entire reason LeaveBalanceService has a batch method.
        $balanceMap = $balances->forEmployees(
            BranchContext::branchId(),
            $allocations->pluck('employee_id')->unique()->values()->all()
        );

        return inertia('Hr/LeaveAllocations/Index', [
            'allocations' => LeaveAllocationResource::collection($allocations),
            'balances' => $balanceMap,
            'filterOptions' => [
                'leaveTypes' => LeaveType::query()->orderBy('name')->get(['id', 'name']),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'filters' => (array) $request->input('filters', []),
            ],
        ]);
    }

    public function store(LeaveAllocationStoreRequest $request)
    {
        LeaveAllocation::create($this->normalizeDates($request->validated()));

        return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.leave_allocation')]));
    }

    public function update(LeaveAllocationUpdateRequest $request, LeaveAllocation $leaveAllocation)
    {
        $leaveAllocation->update($this->normalizeDates($request->validated()));

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('hr.leave_allocation')]));
    }

    public function destroy(Request $request, LeaveAllocation $leaveAllocation)
    {
        // Deleting an allocation that requests are charged against would orphan
        // them and make the balance unreadable.
        if ($leaveAllocation->requests()->exists()) {
            return redirect()->back()->with('error', __('hr.validation.allocation_in_use'));
        }

        $leaveAllocation->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.leave_allocation')]));
    }

    public function restore(Request $request, LeaveAllocation $leaveAllocation)
    {
        $this->authorize('update', $leaveAllocation);
        $leaveAllocation->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.leave_allocation')]));
    }

    public function forceDelete(Request $request, LeaveAllocation $leaveAllocation)
    {
        $this->authorize('delete', $leaveAllocation);
        app(DeletedRecordService::class)->forceDelete('leave_allocations', (string) $leaveAllocation->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.leave_allocation')]));
    }

    private function normalizeDates(array $validated): array
    {
        foreach (['period_start', 'period_end'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = $this->dateConversionService->toGregorian($validated[$field]);
            }
        }

        return $validated;
    }
}
