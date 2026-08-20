<?php

namespace App\Http\Requests\Hr;

use App\Enums\AttendanceDeviceType;
use App\Http\Requests\Concerns\BranchScopedUnique;
use App\Models\Hr\AttendanceDevice;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AttendanceDeviceStoreRequest extends FormRequest
{
    use BranchScopedUnique;

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $device = $this->route('attendance_device');
        $id = $device instanceof AttendanceDevice ? (string) $device->id : ($device ? (string) $device : null);

        return [
            'name' => ['required', 'string', 'max:100'],
            'code' => ['required', 'string', 'max:50', $this->uniqueInBranch('attendance_devices', $id)],
            'device_type' => ['required', Rule::in(AttendanceDeviceType::values())],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'location' => ['nullable', 'string', 'max:150'],
            'ip_address' => ['nullable', 'ip'],
            'is_active' => ['nullable', 'boolean'],
            'remark' => ['nullable', 'string'],
        ];
    }
}
