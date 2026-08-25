<?php

namespace App\Http\Controllers\Hr;

use App\Enums\AttendanceSource;
use App\Enums\PunchDirection;
use App\Http\Controllers\Controller;
use App\Http\Resources\Hr\AttendanceResource;
use App\Http\Resources\Hr\LeaveRequestResource;
use App\Models\Hr\Attendance;
use App\Models\Hr\AttendancePunch;
use App\Models\Hr\Employee;
use App\Models\Hr\HrSetting;
use App\Services\Hr\LeaveBalanceService;
use App\Services\Hr\PunchPairingService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Employee self-service: see my attendance and leave, clock myself in and out.
 *
 * Authorisation is the EnsureEmployeeProfile middleware plus the employee's own
 * self_service_enabled flag — deliberately NOT `attendances.create`. A clerk who
 * records attendance for others should not need this, and an employee clocking
 * themselves in should not thereby gain the ability to edit anyone else's day.
 */
class SelfServiceController extends Controller
{
    private function employee(): Employee
    {
        return app('current_employee');
    }

    public function index(Request $request, LeaveBalanceService $balances)
    {
        $employee = $this->employee();
        $today = Carbon::today();

        $todayRow = Attendance::withoutGlobalScopes()
            ->where('branch_id', $employee->branch_id)
            ->where('employee_id', $employee->id)
            ->whereDate('date', $today->toDateString())
            ->whereNull('deleted_at')
            ->with('shift:id,name')
            ->first();

        $history = Attendance::withoutGlobalScopes()
            ->where('branch_id', $employee->branch_id)
            ->where('employee_id', $employee->id)
            ->whereNull('deleted_at')
            ->whereDate('date', '>=', $today->copy()->subDays(30)->toDateString())
            ->with('shift:id,name')
            ->orderByDesc('date')
            ->get();

        $leave = $employee->leaveRequests()
            ->with(['leaveType:id,name,colour,is_paid'])
            ->orderByDesc('from_date')
            ->limit(10)
            ->get();

        return inertia('Hr/SelfService/Index', [
            'employee' => [
                'id' => $employee->id,
                'code' => $employee->code,
                'full_name' => $employee->full_name,
                'department_name' => $employee->department?->name,
                'designation_name' => $employee->designation?->name,
                'photo_url' => $employee->photo_url,
            ],
            'today' => $todayRow ? new AttendanceResource($todayRow) : null,
            'history' => AttendanceResource::collection($history),
            'balances' => $balances->forEmployee($employee),
            'leaveRequests' => LeaveRequestResource::collection($leave),
            'canCheckIn' => $todayRow?->check_in === null,
            'canCheckOut' => $todayRow?->check_in !== null && $todayRow?->check_out === null,
        ]);
    }

    public function checkIn(Request $request, PunchPairingService $pairing)
    {
        return $this->punch($request, $pairing, PunchDirection::In);
    }

    public function checkOut(Request $request, PunchPairingService $pairing)
    {
        return $this->punch($request, $pairing, PunchDirection::Out);
    }

    private function punch(Request $request, PunchPairingService $pairing, PunchDirection $direction)
    {
        $employee = $this->employee();
        $settings = HrSetting::forBranch($employee->branch_id);

        $validated = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
        ]);

        $this->assertWithinGeofence($settings, $validated);
        $this->assertAllowedIp($settings, $request->ip());

        $now = now();

        AttendancePunch::withoutGlobalScopes()->insertOrIgnore([[
            'id' => (string) Str::ulid(),
            'employee_id' => $employee->id,
            'punched_at' => $now->toDateTimeString(),
            'punch_direction' => $direction->value,
            'source' => AttendanceSource::SelfService->value,
            // Fingerprinted to the minute, so a double-tap on a slow connection
            // is absorbed by the unique index instead of creating two punches.
            'fingerprint' => AttendancePunch::makeFingerprint(
                'self-service',
                $employee->id,
                $now->copy()->startOfMinute()->toIso8601String()
            ),
            'latitude' => $validated['latitude'] ?? null,
            'longitude' => $validated['longitude'] ?? null,
            'ip_address' => $request->ip(),
            'user_agent' => Str::limit((string) $request->userAgent(), 250, ''),
            'is_ignored' => false,
            'branch_id' => $employee->branch_id,
            'created_by' => $request->user()->id,
            'created_at' => $now->toDateTimeString(),
            'updated_at' => $now->toDateTimeString(),
        ]]);

        $pairing->pairForDate($employee, Carbon::today());

        return redirect()->back()->with(
            'success',
            $direction === PunchDirection::In ? __('hr.checked_in') : __('hr.checked_out')
        );
    }

    /**
     * Haversine distance against the branch location.
     *
     * Off by default: a wrong radius locks out an entire workforce on day one,
     * and indoor GPS accuracy in a concrete building is poor enough that this
     * has to be a deliberate choice.
     */
    private function assertWithinGeofence(HrSetting $settings, array $input): void
    {
        if (! $settings->enforce_geofence) {
            return;
        }

        $lat = $input['latitude'] ?? null;
        $lng = $input['longitude'] ?? null;

        if ($lat === null || $lng === null) {
            throw ValidationException::withMessages([
                'latitude' => __('hr.validation.location_required'),
            ]);
        }

        if ($settings->geofence_latitude === null || $settings->geofence_longitude === null) {
            return;
        }

        $distance = $this->haversineMetres(
            (float) $settings->geofence_latitude,
            (float) $settings->geofence_longitude,
            (float) $lat,
            (float) $lng
        );

        $radius = (int) ($settings->geofence_radius_meters ?? 200);

        if ($distance > $radius) {
            throw ValidationException::withMessages([
                'latitude' => __('hr.validation.outside_geofence', ['distance' => round($distance)]),
            ]);
        }
    }

    private function assertAllowedIp(HrSetting $settings, ?string $ip): void
    {
        $ranges = $settings->allowed_ip_ranges ?? [];

        if ($ranges === [] || ! $ip) {
            return;
        }

        foreach ($ranges as $range) {
            if ($this->ipMatches($ip, (string) $range)) {
                return;
            }
        }

        throw ValidationException::withMessages([
            'ip_address' => __('hr.validation.ip_not_allowed'),
        ]);
    }

    private function ipMatches(string $ip, string $range): bool
    {
        if (! str_contains($range, '/')) {
            return $ip === $range;
        }

        [$subnet, $bits] = explode('/', $range, 2);
        $bits = (int) $bits;

        $ipLong = ip2long($ip);
        $subnetLong = ip2long($subnet);

        if ($ipLong === false || $subnetLong === false || $bits < 0 || $bits > 32) {
            return false;
        }

        $mask = $bits === 0 ? 0 : (-1 << (32 - $bits));

        return ($ipLong & $mask) === ($subnetLong & $mask);
    }

    private function haversineMetres(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $earthRadius = 6371000;

        $dLat = deg2rad($lat2 - $lat1);
        $dLng = deg2rad($lng2 - $lng1);

        $a = sin($dLat / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
