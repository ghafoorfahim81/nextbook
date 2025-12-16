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
            'reference_type' => $this->reference_type,
            'reference_id' => $this->reference_id,
            'transactionable' => $this->transactionable,
            'amount' => $this->amount,
            'account_id' => $this->account_id,
            'account' => $this->whenLoaded('account', fn () => new AccountResource($this->account)),
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', $this->currency),
            'rate' => $this->rate,
            'date' => $dateConversionService->toDisplay($this->date),
            'type' => $this->type,
            'remark' => $this->remark,
        ];
    }
}
