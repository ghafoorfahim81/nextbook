<?php

namespace App\Http\Controllers\Hr;

use App\Enums\Gender;
use App\Enums\LeaveAccrualMethod;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\LeaveTypeStoreRequest;
use App\Http\Requests\Hr\LeaveTypeUpdateRequest;
use App\Http\Resources\Hr\LeaveTypeResource;
use App\Models\Hr\LeaveType;
use App\Services\DeletedRecordService;
use Illuminate\Http\Request;

class LeaveTypeController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(LeaveType::class, 'leave_type');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'asc')) === 'desc' ? 'desc' : 'asc';

        $types = LeaveType::query()
            ->with('createdBy:id,name')
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('name', $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/LeaveTypes/Index', [
            'leaveTypes' => LeaveTypeResource::collection($types),
            'filterOptions' => [
                'accrualMethods' => array_map(
                    fn (LeaveAccrualMethod $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    LeaveAccrualMethod::cases()
                ),
                'genders' => array_map(
                    fn (Gender $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    Gender::cases()
                ),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => 'name',
                'sortDirection' => $sortDirection,
            ],
        ]);
    }

    public function store(LeaveTypeStoreRequest $request)
    {
        LeaveType::create($request->validated());

        return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.leave_type')]));
    }

    public function update(LeaveTypeUpdateRequest $request, LeaveType $leaveType)
    {
        $leaveType->update($request->validated());

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('hr.leave_type')]));
    }

    public function destroy(Request $request, LeaveType $leaveType)
    {
        if (! $leaveType->canBeDeleted()) {
            return redirect()->back()->with('error', $leaveType->getDependencyMessage());
        }

        $leaveType->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.leave_type')]));
    }

    public function restore(Request $request, LeaveType $leaveType)
    {
        $this->authorize('update', $leaveType);
        $leaveType->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.leave_type')]));
    }

    public function forceDelete(Request $request, LeaveType $leaveType)
    {
        $this->authorize('delete', $leaveType);
        app(DeletedRecordService::class)->forceDelete('leave_types', (string) $leaveType->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.leave_type')]));
    }
}
