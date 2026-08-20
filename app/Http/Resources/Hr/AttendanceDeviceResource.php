<?php

namespace App\Http\Resources\Hr;

use App\Enums\AttendanceDeviceType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AttendanceDeviceResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->device_type instanceof AttendanceDeviceType
            ? $this->device_type
            : AttendanceDeviceType::tryFrom((string) $this->device_type);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'device_type' => $type?->value,
            'device_type_label' => $type?->getLabel(),
            'serial_number' => $this->serial_number,
            'location' => $this->location,
            'ip_address' => $this->ip_address,
            'is_active' => (bool) $this->is_active,
            'last_sync_at' => $this->last_sync_at?->toDateTimeString(),
            'mapping_count' => $this->whenCounted('mappings'),
            'remark' => $this->remark,
            'created_by' => $this->createdBy?->name,
        ];
    }
}
