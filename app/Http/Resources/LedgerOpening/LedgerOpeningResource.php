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
            'ledgerable_id',
            'ledgerable_type',
            'transaction_id'
        ];
    }
}
