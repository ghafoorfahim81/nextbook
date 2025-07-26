<?php

namespace App\Http\Resources\Administration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BranchResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'is_main' => $this->is_main,
            'parent_id' => $this->parent_id,
            'parent' => $this->parent ? array_merge($this->parent->toArray(), ['name' => $this->parent->name]) : null,
            'location' => $this->location,
            'sub_domain' => $this->sub_domain,
            'remark' => $this->remark,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
