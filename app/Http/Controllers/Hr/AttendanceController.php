<?php

namespace App\Http\Controllers\Hr;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
use App\Http\Controllers\Concerns\ProvidesEmployeeOptions;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\AttendanceRosterRequest;
use App\Http\Resources\Hr\AttendanceResource;
use App\Models\Administration\Department;
use App\Models\Hr\Attendance;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Shift;
use App\Services\ActivityLogService;
use App\Services\DateConversionService;
use App\Services\Hr\AttendanceService;
use App\Services\Hr\PunchImportService;
use App\Support\BranchContext;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AttendanceController extends Controller
{
    use ProvidesEmployeeOptions;

    private DateConversionService $dateConversionService;

    public function __construct(DateConversionService $dateConversionService)
    {
        $this->authorizeResource(Attendance::class, 'attendance');
        $this->dateConversionService = $dateConversionService;
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());
        $sortDirection = strtolower($request->input('sortDirection', 'desc')) === 'asc' ? 'asc' : 'desc';

        $sortableFields = [
            'date' => 'date',
            'status' => 'status',
            'worked_hours' => 'worked_hours',
            'late_minutes' => 'late_minutes',
        ];
        $sortColumn = $sortableFields[$request->input('sortField', 'date')] ?? 'date';

        $attendances = Attendance::query()
            ->with(['employee:id,full_name,code,department_id', 'employee.department:id,name', 'shift:id,name'])
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy($sortColumn, $sortDirection)
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/Attendances/Index', [
            'attendances' => AttendanceResource::collection($attendances),
            'filterOptions' => $this->filterOptions(),
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
                'sortField' => $request->input('sortField', 'date'),
                'sortDirection' => $sortDirection,
                'filters' => (array) $request->input('filters', []),
            ],
        ]);
    }

    /**
     * The bulk roster grid: one department, one day, every employee in a row.
     */
    public function roster(Request $request, AttendanceService $attendance)
    {
        $this->authorize('viewAny', Attendance::class);

        $date = $this->resolveDate($request->input('date'));

        return inertia('Hr/Attendances/Roster', [
            'roster' => $attendance->roster(
                BranchContext::branchId(),
                $date,
                $request->input('department_id'),
                $request->input('shift_id'),
            ),
            'options' => [
                'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
                'shifts' => Shift::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                // The import dialog lives on this screen, so its device list
                // ships with the roster rather than costing a second request.
                'devices' => AttendanceDevice::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
                'statuses' => array_map(
                    fn (AttendanceStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    AttendanceStatus::cases()
                ),
            ],
            'filters' => [
                'date' => $this->dateConversionService->toDisplay($date->toDateString()),
                'department_id' => $request->input('department_id'),
                'shift_id' => $request->input('shift_id'),
            ],
        ]);
    }

    public function storeRoster(AttendanceRosterRequest $request, AttendanceService $attendance, ActivityLogService $activityLog)
    {
        $this->authorize('create', Attendance::class);

        $date = $this->resolveDate($request->input('date'));

        $written = $attendance->upsertRoster($date, $request->input('rows', []), $request->input('shift_id'));

        $activityLog->logAction(
            eventType: 'roster_saved',
            module: 'attendance',
            description: "Attendance roster saved for {$date->toDateString()} ({$written} rows).",
            metadata: ['date' => $date->toDateString(), 'rows' => $written],
        );

        return redirect()->back()->with('success', __('hr.roster_saved', ['count' => $written]));
    }

    /**
     * Punches whose device ID has no employee mapping.
     *
     * These are deliberately kept rather than dropped at import time, so this
     * screen is where they get resolved.
     */
    public function unmappedPunches(Request $request)
    {
        $this->authorize('viewAny', Attendance::class);

        $punches = AttendancePunch::query()
            ->unmapped()
            ->with('device:id,name,code')
            ->orderByDesc('punched_at')
            ->paginate($request->input('perPage', recordsPerPage()))
            ->withQueryString();

        return inertia('Hr/Attendances/UnmappedPunches', [
            'punches' => $punches->through(fn (AttendancePunch $p) => [
                'id' => $p->id,
                'device_name' => $p->device?->name,
                'device_id' => $p->attendance_device_id,
                'device_user_id' => $p->device_user_id,
                'punched_at' => $p->punched_at?->toDateTimeString(),
                'source' => $p->source?->value,
            ]),
            'options' => [
                'devices' => AttendanceDevice::query()->orderBy('name')->get(['id', 'name']),
            ],
        ]);
    }

    public function import(Request $request, PunchImportService $importer, ActivityLogService $activityLog)
    {
        $this->authorize('create', Attendance::class);

        $validated = $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,dat,tsv'],
            'attendance_device_id' => ['nullable', 'exists:attendance_devices,id'],
            'column_device_user_id' => ['required', 'string'],
            'column_timestamp' => ['required', 'string'],
            'column_direction' => ['nullable', 'string'],
        ]);

        $summary = $importer->import(
            $request->file('file'),
            $validated['attendance_device_id'] ?? null,
            [
                'device_user_id' => $validated['column_device_user_id'],
                'timestamp' => $validated['column_timestamp'],
                'direction' => $validated['column_direction'] ?? null,
            ]
        );

        $activityLog->logAction(
            eventType: 'import',
            module: 'attendance',
            description: "Imported {$summary['parsed']} attendance punches.",
            metadata: $summary,
        );

        return redirect()->back()->with('success', __('hr.import_summary', [
            'parsed' => $summary['parsed'],
            'skipped' => $summary['skipped'],
            'unmapped' => $summary['unmapped'],
        ]));
    }

    /**
     * Read the header row so the user can map columns before committing.
     */
    public function importPreview(Request $request, PunchImportService $importer)
    {
        $this->authorize('create', Attendance::class);

        $request->validate([
            'file' => ['required', 'file', 'max:10240', 'mimes:csv,txt,dat,tsv'],
        ]);

        return response()->json([
            'headers' => $importer->detectHeaders($request->file('file')),
        ]);
    }

    public function destroy(Request $request, Attendance $attendance)
    {
        // A day a posted payroll already consumed is frozen; deleting it would
        // leave the payslip describing hours that no longer exist.
        if ($attendance->isLocked()) {
            return redirect()->back()->with('error', __('hr.validation.attendance_locked'));
        }

        $attendance->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.attendance')]));
    }

    public function restore(Request $request, Attendance $attendance)
    {
        $this->authorize('update', $attendance);

        // Restore has to respect the payroll lock exactly as update does.
        // Without this, a day consumed by a posted payroll could be deleted and
        // restored to slip a change past the lock the posting put there.
        if ($attendance->isLocked()) {
            return redirect()->back()->with('error', __('hr.validation.attendance_locked'));
        }

        $attendance->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.attendance')]));
    }

    private function filterOptions(): array
    {
        return [
            'departments' => Department::query()->orderBy('name')->get(['id', 'name']),
            'shifts' => Shift::query()->orderBy('name')->get(['id', 'name']),
            'statuses' => array_map(
                fn (AttendanceStatus $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                AttendanceStatus::cases()
            ),
            'sources' => array_map(
                fn (AttendanceSource $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                AttendanceSource::cases()
            ),
            'employees' => $this->employeeOptions(),
        ];
    }

    private function resolveDate(?string $input): Carbon
    {
        if (! $input) {
            return Carbon::today();
        }

        return Carbon::parse($this->dateConversionService->toGregorian($input));
    }
}
