<?php

namespace App\Http\Resources\Administration;

use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name_en' => $this->name_en,
            'name_fa' => $this->name_fa,
            'name_pa' => $this->name_pa,
            'abbreviation' => $this->abbreviation,
            'address' => $this->address,
            'phone' => $this->phone,
            'country' => $this->country,
            'city' => $this->city,
            'logo' => $this->logo,
            'calendar_type' => $this->calendar_type,
            'working_style' => $this->working_style,
            'business_type' => $this->business_type,
            'locale' => $this->locale,
            'currency_id' => $this->currency_id,
            'currency' => $this->currency,
            'email' => $this->email,
            'website' => $this->website,
            'invoice_description' => $this->invoice_description,
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
