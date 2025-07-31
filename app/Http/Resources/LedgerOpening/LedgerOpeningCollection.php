<?php

namespace App\Http\Resources\LedgerOpening;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

class LedgerOpeningCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
