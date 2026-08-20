<?php

namespace App\Http\Controllers\Hr;

use App\Enums\LeaveRequestStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\LeaveRequestStoreRequest;
use App\Http\Requests\Hr\LeaveRequestUpdateRequest;
use App\Http\Resources\Hr\LeaveRequestResource;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\LeaveType;
use App\Services\ActivityLogService;
use App\Services\AttachmentService;
use App\Services\DateConversionService;
use App\Services\DeletedRecordService;
use App\Services\Hr\LeaveBalanceService;
use App\Services\Hr\LeaveRequestService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class LeaveRequestController extends Controller
{
    private DateConversionService $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(LeaveRequest::class, 'leave_request');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortableFields = [
            'number' => 'number',
            'from_date' => 'from_date',
            'days' => 'days',
            'status' => 'status',
        ];
        $sortColumn = $sortableFields[$request->input('sortField', 'from_date')] ?? 'from_date';

        $requests = LeaveRequest::query()
            ->with(['employee:id,full_name,code', 'leaveType:id,name,colour,is_paid', 'approver:id,name', 'createdBy:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/LeaveRequests/Index', [
            'leaveRequests' => LeaveRequestResource::collection($requests),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $request->input('sortField', 'from_date'),
                'sortDirection' => $sortDirection,
                'filters' => (array) $request->input('filters', []),
            ],
        ]);
    }

    public function create()
    {
        return inertia('Hr/LeaveRequests/Create', [
            'nextNumber' => LeaveRequest::nextNumber(),
            'options' => $this->filterOptions(),
        ]);
    }

    public function store(
        LeaveRequestStoreRequest $request,
        LeaveRequestService $leave,
        AttachmentService $attachments,
        ActivityLogService $activityLog
    ) {
        $leaveRequest = DB::transaction(function () use ($request, $leave, $attachments, $activityLog) {
            $validated = $this->normalizeDates($request->validated());
            unset($validated['documents']);

            $employee = Employee::findOrFail($validated['employee_id']);
            $type = LeaveType::findOrFail($validated['leave_type_id']);

            $from = Carbon::parse($validated['from_date']);
            $to = Carbon::parse($validated['to_date']);

            // Day count is computed server-side from the leave type's own
            // weekend and holiday rules — never taken from the form, or the
            // balance could be spent at a rate the policy does not allow.
            $validated['days'] = $leave->countLeaveDays($employee, $type, $from, $to, (bool) ($validated['is_half_day'] ?? false));
            $validated['number'] = LeaveRequest::nextNumber();
            $validated['status'] = LeaveRequestStatus::Draft->value;

            $leaveRequest = LeaveRequest::create($validated);

            if ($request->hasFile('documents')) {
                $attachments->store($leaveRequest, $request->file('documents'));
            }

            $activityLog->logCreate(
                reference: $leaveRequest,
                module: 'leave_request',
                description: "Leave request #{$leaveRequest->number} created.",
                newValues: $leaveRequest->only(['number', 'employee_id', 'leave_type_id', 'from_date', 'to_date', 'days']),
            );

            // Submitting straight away is the common case; the draft state
            // exists for someone preparing a request they are not ready to file.
            if ($request->boolean('submit')) {
                $leave->transition($leaveRequest, LeaveRequestStatus::Pending);
            }

            return $leaveRequest;
        });

        return redirect()->route('leave-requests.show', $leaveRequest->id)
            ->with('success', __('general.created_successfully', ['resource' => __('hr.leave_request')]));
    }

    public function show(Request $request, LeaveRequest $leaveRequest, LeaveBalanceService $balances)
    {
        $leaveRequest->load([
            // branch_id is required here even though the page never shows it —
            // LeaveBalanceService scopes its query by the employee's branch,
            // and a constrained select that omits it hands the service a null.
            'employee:id,branch_id,full_name,code,department_id,gender,joining_date',
            'employee.department:id,name',
            'leaveType', 'approver:id,name', 'handoverTo:id,full_name', 'attachments', 'createdBy:id,name',
        ]);

        return inertia('Hr/LeaveRequests/Show', [
            'leaveRequest' => new LeaveRequestResource($leaveRequest),
            'balance' => $leaveRequest->employee
                ? $balances->forType($leaveRequest->employee, $leaveRequest->leave_type_id, $leaveRequest->from_date)
                : null,
        ]);
    }

    public function edit(Request $request, LeaveRequest $leaveRequest)
    {
        $leaveRequest->load(['employee:id,full_name,code', 'leaveType:id,name', 'attachments']);

        return inertia('Hr/LeaveRequests/Edit', [
            'leaveRequest' => new LeaveRequestResource($leaveRequest),
            'options' => $this->filterOptions(),
        ]);
    }

    public function update(
        LeaveRequestUpdateRequest $request,
        LeaveRequest $leaveRequest,
        LeaveRequestService $leave,
        AttachmentService $attachments
    ) {
        // Once approved or rejected the dates are settled; editing them would
        // silently change what was agreed.
        if (! in_array($leaveRequest->statusEnum(), [LeaveRequestStatus::Draft, LeaveRequestStatus::Pending], true)) {
            return redirect()->back()->with('error', __('hr.validation.leave_not_editable'));
        }

        DB::transaction(function () use ($request, $leaveRequest, $leave, $attachments) {
            $validated = $this->normalizeDates($request->validated());
            unset($validated['documents']);

            $employee = Employee::findOrFail($validated['employee_id']);
            $type = LeaveType::findOrFail($validated['leave_type_id']);

            $validated['days'] = $leave->countLeaveDays(
                $employee,
                $type,
                Carbon::parse($validated['from_date']),
                Carbon::parse($validated['to_date']),
                (bool) ($validated['is_half_day'] ?? false)
            );

            $leaveRequest->update($validated);

            if ($request->hasFile('documents')) {
                $attachments->store($leaveRequest, $request->file('documents'));
            }
        });

        return redirect()->route('leave-requests.show', $leaveRequest->id)
            ->with('success', __('general.updated_successfully', ['resource' => __('hr.leave_request')]));
    }

    public function submit(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $leave)
    {
        $this->authorize('update', $leaveRequest);

        $leave->transition($leaveRequest, LeaveRequestStatus::Pending);

        return redirect()->back()->with('success', __('hr.leave_submitted'));
    }

    public function approve(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $leave, ActivityLogService $activityLog)
    {
        $this->authorize('approve', $leaveRequest);

        $leave->transition($leaveRequest, LeaveRequestStatus::Approved);

        $activityLog->logAction(
            eventType: 'approved',
            reference: $leaveRequest,
            module: 'leave_request',
            description: "Leave request #{$leaveRequest->number} approved.",
        );

        return redirect()->back()->with('success', __('hr.leave_approved'));
    }

    public function reject(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $leave, ActivityLogService $activityLog)
    {
        $this->authorize('reject', $leaveRequest);

        $validated = $request->validate([
            'rejection_reason' => ['nullable', 'string', 'max:500'],
        ]);

        $leave->transition($leaveRequest, LeaveRequestStatus::Rejected, [
            'reason' => $validated['rejection_reason'] ?? null,
        ]);

        $activityLog->logAction(
            eventType: 'rejected',
            reference: $leaveRequest,
            module: 'leave_request',
            description: "Leave request #{$leaveRequest->number} rejected.",
        );

        return redirect()->back()->with('success', __('hr.leave_rejected'));
    }

    public function cancel(Request $request, LeaveRequest $leaveRequest, LeaveRequestService $leave)
    {
        $this->authorize('update', $leaveRequest);

        $leave->transition($leaveRequest, LeaveRequestStatus::Cancelled);

        return redirect()->back()->with('success', __('hr.leave_cancelled'));
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest)
    {
        // Only a draft can be deleted outright. Anything filed leaves a record
        // — cancelling is how you take back a request someone has seen.
        if ($leaveRequest->statusEnum() !== LeaveRequestStatus::Draft) {
            return redirect()->back()->with('error', __('hr.validation.only_draft_deletable'));
        }

        $leaveRequest->delete();

        return redirect()->route('leave-requests.index')
            ->with('success', __('general.deleted_successfully', ['resource' => __('hr.leave_request')]));
    }

    public function restore(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorize('update', $leaveRequest);
        $leaveRequest->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.leave_request')]));
    }

    public function forceDelete(Request $request, LeaveRequest $leaveRequest)
    {
        $this->authorize('delete', $leaveRequest);
        app(DeletedRecordService::class)->forceDelete('leave_requests', (string) $leaveRequest->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.leave_request')]));
    }

    private function filterOptions(): array
    {
        return [
            'leaveTypes' => LeaveType::query()->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'colour', 'is_paid', 'requires_attachment', 'applicable_gender']),
            'statuses' => array_map(
                fn (LeaveRequestStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                LeaveRequestStatus::cases()
            ),
        ];
    }

    private function normalizeDates(array $validated): array
    {
        foreach (['from_date', 'to_date'] as $field) {
            if (! empty($validated[$field])) {
                $validated[$field] = $this->dateConversionService->toGregorian($validated[$field]);
            }
        }

        return $validated;
    }
}
