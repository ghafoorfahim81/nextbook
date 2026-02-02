<?php

namespace App\Http\Resources\Ledger;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Enums\TransactionType;
class LedgerOpeningResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'amount' => $this->transaction->lines[0]?->credit>0 ? $this->transaction->lines[0]?->credit : $this->transaction->lines[0]?->debit,
            'transaction_type' => $this->transaction->lines[0]?->credit>0 ? TransactionType::CREDIT->value : TransactionType::DEBIT->value,
            'lines' => $this->transaction->lines,
            'rate' => $this->transaction?->rate,
            'currency_id' => $this->transaction?->currency_id,
            'currency' => $this->transaction?->currency,
            'date' => $this->transaction?->date ? $dateConversionService->toDisplay($this->transaction?->date) : null,
            'type' => $this->transaction?->type,
            'transaction' => $this->transaction,
        ];
    }
}
