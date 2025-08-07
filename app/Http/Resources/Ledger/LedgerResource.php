<?php

namespace App\Http\Resources\Ledger;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'address' => $this->address,
            'contact_person' => $this->contact_person,
            'phone_no' => $this->phone_no,
            'email' => $this->email,
            'currency_id' => $this->currency_id,
            'currency' => $this->currency,
            'type' => $this->type,
        ];
    }
}
