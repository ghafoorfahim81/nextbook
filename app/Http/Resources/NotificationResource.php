<?php

namespace App\Http\Resources;

use App\Enums\NotificationCategory;
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
            'category' => NotificationCategory::forType($this->type)->value,
            'title' => $this->title,
            'message' => $this->message,
            'is_read' => (bool) $this->is_read,
            'is_archived' => $this->archived_at !== null,
            'is_favorite' => $this->favorited_at !== null,
            'data' => $this->data,
            'created_at' => $this->created_at?->toIso8601String(),
            'created_at_human' => $this->created_at instanceof CarbonInterface
                ? $this->created_at->diffForHumans()
                : null,
        ];
    }
}
