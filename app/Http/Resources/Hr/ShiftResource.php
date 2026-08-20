<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ShiftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            // Trimmed to HH:MM — the seconds Postgres returns are noise in a
            // time picker and break `date_format:H:i` on the way back in.
            'start_time' => substr((string) $this->start_time, 0, 5),
            'end_time' => substr((string) $this->end_time, 0, 5),
            'crosses_midnight' => (bool) $this->crosses_midnight,
            'break_minutes' => (int) $this->break_minutes,
            'grace_in_minutes' => (int) $this->grace_in_minutes,
            'grace_out_minutes' => (int) $this->grace_out_minutes,
            'full_day_hours' => (float) $this->full_day_hours,
            'half_day_hours' => $this->half_day_hours !== null ? (float) $this->half_day_hours : null,
            'working_days' => array_map('intval', $this->working_days ?? []),
            'is_default' => (bool) $this->is_default,
            'is_active' => (bool) $this->is_active,
            'remark' => $this->remark,
            'employee_count' => $this->whenCounted('employees'),
            'created_by' => $this->createdBy?->name,
        ];
    }
}
