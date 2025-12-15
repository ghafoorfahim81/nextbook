<?php

namespace App\Http\Resources\LedgerOpening;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LedgerOpeningResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'amount' => $this->transaction?->amount,
            'rate' => $this->transaction?->rate,
            'currency_id' => $this->transaction?->currency_id,
            'currency' => $this->transaction?->currency,
            'date' => $this->transaction?->date,
            'type' => $this->transaction?->type,
        ];
    }
}
