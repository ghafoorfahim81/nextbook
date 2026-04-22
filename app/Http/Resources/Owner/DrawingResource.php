<?php

namespace App\Http\Resources\Owner;

use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrawingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $transaction = $this->relationLoaded('transaction') ? $this->transaction : null;
        $lines = $transaction?->lines ?? collect();
        $creditLine = $lines->first(fn ($line) => (float) ($line->credit ?? 0) > 0);
        $debitLine = $lines->first(fn ($line) => (float) ($line->debit ?? 0) > 0);

        $amount = $creditLine ? ((float) $creditLine->credit > 0 ? $creditLine->credit : $creditLine->debit) : ($debitLine ? ($debitLine->debit ?: $debitLine->credit) : 0);

        return [
            'id' => $this->id,
            'owner_id' => $this->owner_id,
            'owner' => OwnerResource::make($this->whenLoaded('owner')), 
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'narration' => $this->narration,
            'amount' => $amount,
            'currency_id' => $transaction?->currency_id,
            'currency' => $transaction?->relationLoaded('currency') && $transaction->currency
                ? CurrencyResource::make($transaction->currency)
                : null,
            'rate' => $transaction?->rate,
            'bank_account_id' => $creditLine?->account_id,
            'bank_account' => $creditLine?->relationLoaded('account') && $creditLine->account
                ? new AccountResource($creditLine->account)
                : null,
            'drawing_account_id' => $debitLine?->account_id,
            'drawing_account' => $debitLine?->relationLoaded('account') && $debitLine->account
                ? new AccountResource($debitLine->account)
                : null,
            'transaction_id' => $transaction?->id,
            'transaction' => $transaction ? new TransactionResource($transaction) : null,
            'branch_id' => $this->branch_id,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }
}
