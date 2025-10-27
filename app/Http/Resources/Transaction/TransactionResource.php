<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'transactionable' => $this->transactionable,
            'amount' => $this->amount,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', $this->currency),
            'rate' => $this->rate,
            'date' => $this->date,
            'type' => $this->type,
            'remark' => $this->remark, 
        ];
    }
}
