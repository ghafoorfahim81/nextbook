<?php

namespace App\Http\Resources\Receipt;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'ledger_id' => $this->ledger_id,
            'ledger' => $this->whenLoaded('ledger'),
            'ledger_name' => $this->ledger?->name,
            'amount' => $this->bankTransaction?->amount,
            'currency_id' => $this->bankTransaction?->currency_id,
            'currency_code' => $this->bankTransaction?->currency?->code,
            'rate' => $this->bankTransaction?->rate,
            'bank_account_id' => $this->bankTransaction?->account?->id,
            'bank_account' => new AccountResource($this->bankTransaction?->account),
            'cheque_no' => $this->cheque_no,
            'narration' => $this->narration,
            'receive_transaction_id' => $this->receive_transaction_id,
            'bank_transaction_id' => $this->bank_transaction_id, 
            'bank_transaction' => new TransactionResource($this->bankTransaction),
            'receive_transaction' => new TransactionResource($this->receiveTransaction),
        ];
    }
}


