<?php

namespace App\Http\Resources\Inventory;

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LandedCostResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);

        $allocationMethod = $this->allocation_method instanceof LandedCostAllocationMethod
            ? $this->allocation_method
            : LandedCostAllocationMethod::tryFrom((string) $this->allocation_method);

        $status = $this->status instanceof LandedCostStatus
            ? $this->status
            : LandedCostStatus::tryFrom((string) $this->status);

        return [
            'id' => $this->id,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'purchase_id' => $this->purchase_id,
            'purchase_number' => $this->purchase?->number,
            'purchase' => PurchaseResource::make($this->whenLoaded('purchase')),
            'total_cost' => $this->total_cost,
            'allocated_total' => $this->allocated_total,
            'allocation_method' => $allocationMethod?->getLabel() ?? $this->allocation_method,
            'allocation_method_id' => $allocationMethod?->value ?? $this->allocation_method,
            'status' => $status?->getLabel() ?? $this->status,
            'status_id' => $status?->value ?? $this->status,
            'notes' => $this->notes,
            'items' => LandedCostItemResource::collection($this->whenLoaded('items')),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
