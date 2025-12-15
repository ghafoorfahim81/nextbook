<?php

namespace App\Http\Resources\AccountTransfer;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\ChartOFAccountResource;

class AccountTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'remark' => $this->remark,
            'from_transaction_id' => $this->from_transaction_id,
            'to_transaction_id' => $this->to_transaction_id,
            'from_transaction' => new TransactionResource($this->whenLoaded('fromTransaction')),
            'to_transaction' => new TransactionResource($this->whenLoaded('toTransaction')),
            // Convenience derived fields for Index
            'from_account_name' => $this->fromTransaction?->account?->name,
            'to_account_name' => $this->toTransaction?->account?->name,
            'amount' => $this->toTransaction?->amount ?? $this->fromTransaction?->amount,
            'currency_code' => $this->toTransaction?->currency?->code ?? $this->fromTransaction?->currency?->code,
        ];
    }
}


