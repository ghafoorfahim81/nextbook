<?php

namespace App\Http\Resources\Administration;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CurrencyRateUpdateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'exchange_rate' => $this->exchange_rate,
            'date' => $this->date?->format('Y-m-d'),
            'currency' => [
                'id' => $this->currency?->id,
                'name' => $this->currency?->name,
                'code' => $this->currency?->code,
                'symbol' => $this->currency?->symbol,
                'format' => $this->currency?->format,
                'flag' => $this->currency?->flag,
            ],
        ];
    }
}
