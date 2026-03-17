<?php

namespace App\Http\Resources;

use Carbon\CarbonInterface;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'message' => $this->message,
            'is_read' => (bool) $this->is_read,
            'data' => $this->data,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at instanceof CarbonInterface
                ? $this->created_at->diffForHumans()
                : null,
        ];
    }
}
