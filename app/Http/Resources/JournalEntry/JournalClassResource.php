<?php

namespace App\Http\Resources\JournalEntry;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalClassResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'description' => $this->description,
        ];
    }
}
