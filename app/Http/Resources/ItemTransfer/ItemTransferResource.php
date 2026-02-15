<?php

namespace App\Http\Resources\ItemTransfer;

use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemTransferResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(DateConversionService::class);

        return [
            'id' => $this->id,
            'date' => $dateConversionService->toDisplay($this->date),
            'from_store_id' => $this->from_store_id,
            'from_store' => $this->whenLoaded('fromStore', fn() => [
                'id' => $this->fromStore->id,
                'name' => $this->fromStore->name,
            ]),
            'to_store_id' => $this->to_store_id,
            'to_store' => $this->whenLoaded('toStore', fn() => [
                'id' => $this->toStore->id,
                'name' => $this->toStore->name,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'transfer_cost' => $this->transfer_cost,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch'),
            'remarks' => $this->remarks,
            'items' => ItemTransferItemResource::collection($this->whenLoaded('items')),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
