<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The minimal shape a NextSelect option needs.
 *
 * Kept separate from EmployeeListResource so a type-ahead never ships salary,
 * bank details or national ID to the browser just to render a dropdown row.
 */
class EmployeeOptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->full_name,
            'code' => $this->code,
            'phone_number' => $this->phone_number,
            'email' => $this->email,
            'department_name' => $this->department?->name,
            'designation_name' => $this->designation?->name,
            'currency_id' => $this->currency_id,
            'is_active' => (bool) $this->is_active,
        ];
    }
}
