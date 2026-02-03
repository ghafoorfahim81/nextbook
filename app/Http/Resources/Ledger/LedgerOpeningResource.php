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
        if ($this->resource === null) {
            return [
                'id' => null,
                'amount' => 0,
                'lines' => [],
                'rate' => null,
                'currency_id' => null,
                'currency' => null,
                'date' => null,
                'type' => null,
                'transaction' => null,
            ];
        }

        $dateConversionService = app(\App\Services\DateConversionService::class);
        $transaction = $this->transaction;
        $firstLine = $transaction?->lines?->first();

        return [
            'id' => $this->id,
            'amount' => ($firstLine?->credit ?? 0) > 0 ? ($firstLine?->credit ?? 0) : ($firstLine?->debit ?? 0),
            'lines' => $transaction?->lines ?? [],
            'rate' => $transaction?->rate,
            'currency_id' => $transaction?->currency_id,
            'currency' => $transaction?->currency,
            'date' => $transaction?->date ? $dateConversionService->toDisplay($transaction->date) : null,
            'type' => $transaction?->type,
            'transaction' => $transaction,
        ];
    }
}
