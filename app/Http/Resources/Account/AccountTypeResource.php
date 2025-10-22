<?php

namespace App\Http\Resources\Account;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AccountTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'remark' => $this->remark,
            'slug' => $this->slug,
            'is_main' => $this->is_main,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
