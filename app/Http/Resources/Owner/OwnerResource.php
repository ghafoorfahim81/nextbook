<?php

namespace App\Http\Resources\Owner;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'nic' => $this->nic,
            'email' => $this->email,
            'address' => $this->address,
            'phone_number' => $this->phone_number,
            'ownership_percentage' => $this->ownership_percentage,
            'is_active' => $this->is_active,
            'capital_transaction_id' => $this->capital_transaction_id,
            'account_transaction_id' => $this->account_transaction_id,
            'capital_account_id' => $this->capital_account_id,
            'drawing_account_id' => $this->drawing_account_id,
            'capital_account' => $this->whenLoaded('capitalAccount'),
            'drawing_account' => $this->whenLoaded('drawingAccount'),
            'capital_transaction' => $this->whenLoaded('capitalTransaction'),
            'account_transaction' => $this->whenLoaded('accountTransaction'),
        ];
    }
}


