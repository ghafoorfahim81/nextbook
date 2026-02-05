<?php

namespace App\Http\Resources\AccountTransfer;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
class AccountTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $fromAccount = $this->getFromAccountAttribute();
        $toAccount = $this->getToAccountAttribute();
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'remark' => $this->remark,
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
            // Convenience derived fields for Index
            'from_account_name' => $fromAccount?->name,
            'to_account_name' => $toAccount?->name,
            'from_account_id' => $fromAccount?->id,
            'to_account_id' => $toAccount?->id,
            'from_account' => new AccountResource($fromAccount),
            'to_account' => new AccountResource($toAccount),
            'amount' => $this->transaction?->lines?->first()?->debit>0?$this->transaction?->lines?->first()?->debit: $this->transaction?->lines?->first()?->credit,
            'currency_code' => $this->transaction?->currency?->code,
            'currency_id' => $this->transaction?->currency?->id,
            'rate' => $this->transaction?->rate,
            'currency' => ($this->transaction?->currency),
        ];
    }

    public function getFromAccountAttribute()
    {
        if($this->transaction?->lines?->first()?->account?->accountType?->nature == 'asset' ||
         $this->transaction?->lines?->first()?->account?->accountType?->nature == 'expense' ) {
            return $this->transaction?->lines?->first()?->account;
        } else {
            return $this->transaction?->lines?->last()?->account;
        }
    }

    public function getToAccountAttribute()
    {
        if(!$this->transaction?->lines?->first()?->account?->accountType?->nature == 'asset' ||
         !$this->transaction?->lines?->first()?->account?->accountType?->nature == 'expense' ) {
            return $this->transaction?->lines?->first()?->account;
        } else {
            return $this->transaction?->lines?->last()?->account;
        }
    }
}


