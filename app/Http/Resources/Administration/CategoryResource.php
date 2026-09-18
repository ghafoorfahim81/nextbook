<?php

namespace App\Http\Resources\Administration;

use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CategoryResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'local_name' => $this->local_name,
            'localized_name' => $this->localized_name,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent ? array_merge($this->parent->toArray(), [
                'name' => $this->parent->name,
                'local_name' => $this->parent->local_name,
                'localized_name' => $this->parent->localized_name,
            ]) : null,
            'remark' => $this->remark,
            'is_active' => $this->is_active,
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
