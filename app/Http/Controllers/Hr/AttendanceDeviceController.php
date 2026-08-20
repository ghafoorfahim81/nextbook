<?php

namespace App\Http\Controllers\Hr;

use App\Enums\AttendanceDeviceType;
use App\Http\Controllers\Controller;
use App\Http\Requests\Hr\AttendanceDeviceStoreRequest;
use App\Http\Requests\Hr\AttendanceDeviceUpdateRequest;
use App\Http\Resources\Hr\AttendanceDeviceResource;
use App\Models\Hr\AttendanceDevice;
use App\Models\Hr\AttendanceDeviceUser;
use App\Services\DeletedRecordService;
use App\Services\Hr\PunchImportService;
use Illuminate\Http\Request;

class AttendanceDeviceController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(AttendanceDevice::class, 'attendance_device');
    }

    public function index(Request $request)
    {
        $perPage = $request->input('perPage', recordsPerPage());

        $devices = AttendanceDevice::query()
            ->withCount('mappings')
            ->with('createdBy:id,name')
            ->search($request->query('search'))
            ->filter((array) $request->input('filters', []))
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Hr/AttendanceDevices/Index', [
            'devices' => AttendanceDeviceResource::collection($devices),
            'filterOptions' => [
                'deviceTypes' => array_map(
                    fn (AttendanceDeviceType $c) => ['id' => $c->value, 'name' => $c->getLabel()],
                    AttendanceDeviceType::cases()
                ),
            ],
            'filters' => [
                'search' => $request->query('search'),
                'perPage' => $perPage,
            ],
        ]);
    }

    public function store(AttendanceDeviceStoreRequest $request)
    {
        AttendanceDevice::create($request->validated());

        return redirect()->back()->with('success', __('general.created_successfully', ['resource' => __('hr.device')]));
    }

    public function update(AttendanceDeviceUpdateRequest $request, AttendanceDevice $attendanceDevice)
    {
        $attendanceDevice->update($request->validated());

        return redirect()->back()->with('success', __('general.updated_successfully', ['resource' => __('hr.device')]));
    }

    public function destroy(Request $request, AttendanceDevice $attendanceDevice)
    {
        if (! $attendanceDevice->canBeDeleted()) {
            return redirect()->back()->with('error', $attendanceDevice->getDependencyMessage());
        }

        $attendanceDevice->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.device')]));
    }

    public function restore(Request $request, AttendanceDevice $attendanceDevice)
    {
        $this->authorize('update', $attendanceDevice);
        $attendanceDevice->restore();

        return redirect()->back()->with('success', __('general.restored_successfully', ['resource' => __('hr.device')]));
    }

    public function forceDelete(Request $request, AttendanceDevice $attendanceDevice)
    {
        $this->authorize('delete', $attendanceDevice);
        app(DeletedRecordService::class)->forceDelete('attendance_devices', (string) $attendanceDevice->id);

        return redirect()->back()->with('success', __('general.permanently_deleted_successfully', ['resource' => __('hr.device')]));
    }

    /**
     * Map a terminal user number to an employee, then retro-apply it.
     *
     * Applying immediately is what makes the unmapped-punches screen useful:
     * the punches that were already imported get their employee and their day
     * recomputed, without the file having to be uploaded again.
     */
    public function storeMapping(Request $request, PunchImportService $importer)
    {
        $this->authorize('create', AttendanceDevice::class);

        $validated = $request->validate([
            'attendance_device_id' => ['required', 'exists:attendance_devices,id'],
            'employee_id' => ['required', 'exists:employees,id'],
            'device_user_id' => ['required', 'string', 'max:50'],
        ]);

        $mapping = AttendanceDeviceUser::updateOrCreate(
            [
                'attendance_device_id' => $validated['attendance_device_id'],
                'device_user_id' => $validated['device_user_id'],
            ],
            ['employee_id' => $validated['employee_id']]
        );

        $applied = $importer->applyMapping($mapping);

        return redirect()->back()->with('success', __('hr.mapping_applied', ['count' => $applied]));
    }

    public function destroyMapping(Request $request, AttendanceDeviceUser $mapping)
    {
        $this->authorize('delete', $mapping->device ?? AttendanceDevice::class);

        $mapping->delete();

        return redirect()->back()->with('success', __('general.deleted_successfully', ['resource' => __('hr.mapping')]));
    }
}
