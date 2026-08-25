<?php

namespace App\Http\Resources\Administration;

use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DesignationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'department_id' => $this->department_id,
            'department_name' => $this->department?->name,
            'grade_level' => $this->grade_level,
            'remark' => $this->remark,
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
