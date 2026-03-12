<?php
namespace App\Http\Resources\Administration;

use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BrandResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'legal_name' => $this->legal_name,
            'registration_number' => $this->registration_number,
            'logo' => $this->logo,
            'email' => $this->email,
            'phone' => $this->phone,
            'website' => $this->website,
            'industry' => $this->industry,
            'type' => $this->type,
            'address' => $this->address,
            'city' => $this->city,
            'country' => $this->country,
            'branch_id' => $this->branch_id,
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
