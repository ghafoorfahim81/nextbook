<?php

namespace App\Http\Resources\Transaction;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'voucher_number' => $this->voucher_number,
            'status' => $this->status,
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id, 
            'posted_at' => $this->posted_at?->toISOString(),
            'posted_by' => $this->posted_by,
            'reversal_of_id' => $this->reversal_of_id,
            'reversed_at' => $this->reversed_at?->toISOString(),
            'reversal_reason' => $this->reversal_reason,
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', $this->currency),
            'rate' => $this->rate,
            'date' => $dateConversionService->toDisplay($this->date),
            'remark' => $this->remark,
            'lines' => TransactionLineResource::collection($this->whenLoaded('lines')),
        ];
    }
}
