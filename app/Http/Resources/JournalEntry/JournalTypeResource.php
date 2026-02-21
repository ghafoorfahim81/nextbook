<?php

namespace App\Http\Resources\JournalEntry;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JournalTypeResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
        ];
    }
}
