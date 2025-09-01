<?php

namespace App\Http\Resources\Purchase;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PurchaseResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'supplier_id' => $this->supplier_id,
            'supplier' => $this->supplier->name,
            'date' => $this->date,
            'transaction_id' => $this->transaction_id,
            'amount' => $this->transaction->amount,
            'discount' => $this->discount,
            'discount_type' => $this->discount_type,
            'type' => $this->type,
            'description' => $this->description,
            'status' => $this->status, 
        ];
    }
}
