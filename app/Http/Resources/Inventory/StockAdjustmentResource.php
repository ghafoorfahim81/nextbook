<?php

namespace App\Http\Resources\Inventory;

use App\Enums\TransactionStatus;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class StockAdjustmentResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(DateConversionService::class);
        $status = TransactionStatus::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'date' => $dateConversionService->toDisplay($this->date),
            'type' => $this->type?->value,
            'type_label' => $this->type?->getLabel(),
            'reason' => $this->reason?->value,
            'reason_label' => $this->reason?->getLabel(),
            'warehouse_id' => $this->warehouse_id,
            'warehouse' => $this->whenLoaded('warehouse', fn () => [
                'id' => $this->warehouse->id,
                'name' => $this->warehouse->name,
            ]),
            'status' => $this->status,
            'status_label' => $status?->getLabel() ?? (string) $this->status,
            'notes' => $this->notes,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch'),
            'total_cost' => $this->whenLoaded('items', fn () => $this->items->sum(
                fn ($item) => (float) $item->quantity * (float) ($item->unit_cost ?? 0)
            )),
            'items' => StockAdjustmentItemResource::collection($this->whenLoaded('items')),
            'transaction' => $this->whenLoaded('transaction'),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
