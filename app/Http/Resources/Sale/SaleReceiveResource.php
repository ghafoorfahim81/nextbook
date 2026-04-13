<?php

namespace App\Http\Resources\Sale;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SaleReceiveResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sale_id' => $this->sale_id,
            'receipt_id' => $this->receipt_id,
            'amount' => $this->amount,
            'sale' => $this->whenLoaded('sale'),
            'receipt' => $this->whenLoaded('receipt'),
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
            'deleted_by' => $this->deleted_by,
        ];
    }
}
