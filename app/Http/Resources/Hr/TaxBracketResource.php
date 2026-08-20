<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxBracketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'sequence' => (int) $this->sequence,
            'from_amount' => (float) $this->from_amount,
            // Null means "and everything above". The top band has no ceiling,
            // and 999,999,999 would be a lie that eventually bites.
            'to_amount' => $this->to_amount !== null ? (float) $this->to_amount : null,
            'fixed_amount' => (float) $this->fixed_amount,
            'rate' => (float) $this->rate,
        ];
    }
}
