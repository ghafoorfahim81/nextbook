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
            'amount' => $this->transaction?->lines[0]->debit>0?$this->transaction?->lines[0]->debit: $this->transaction?->lines[0]->credit,
            'currency_id' => $this->transaction?->currency_id,
            'currency_code' => $this->transaction?->currency?->code, 
            'rate' => $this->transaction?->rate,
            'cheque_no' => $this->cheque_no,
            'narration' => $this->narration,
            'transaction_id' => $this->transaction_id,
            'bank_account_id' => $this->transaction?->lines[0]->account_id,
            'bank_account' => new AccountResource($this->transaction?->lines[0]->account),
            'transaction' => new TransactionResource($this->transaction),
        ];
    }
}


