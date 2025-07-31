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
            'transactionable' => $this->transactionable,
            'ledgerable' => $this->ledgerable,
            'created_by' => $this->created_by,
            'updated_by' => $this->updated_by,
        ];
    }
}
