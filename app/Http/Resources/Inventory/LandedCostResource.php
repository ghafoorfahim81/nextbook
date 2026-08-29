<?php

namespace App\Http\Resources\Inventory;

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use App\Http\Resources\Purchase\PurchaseResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Http\Resources\AttachmentResource;
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
            'purchase_ids' => $this->whenLoaded('purchases', fn () => $this->purchases->pluck('id')->values()->all(), $this->purchase_id ? [$this->purchase_id] : []),
            'purchase_number' => $this->whenLoaded('purchases', fn () => $this->purchases->pluck('number')->filter()->join(', '), $this->purchase?->number),
            'purchase_numbers' => $this->whenLoaded('purchases', fn () => $this->purchases->pluck('number')->filter()->values()->all(), $this->purchase?->number ? [$this->purchase?->number] : []),
            'purchase' => PurchaseResource::make($this->whenLoaded('purchase')),
            'purchases' => PurchaseResource::collection($this->whenLoaded('purchases')),
            'bank_account_id' => $this->bank_account_id,
            'bank_account_name' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->name),
            // Currency and rate are never stored on the landed cost itself —
            // they live on its transaction, and are read from there for every
            // CRUD operation, per the accounting boundary in TransactionService.
            'currency_id' => $this->whenLoaded('transaction', fn () => $this->transaction?->currency_id),
            'currency' => $this->whenLoaded('transaction', fn () => $this->transaction?->currency),
            'currency_code' => $this->whenLoaded('transaction', fn () => $this->transaction?->currency?->code),
            'rate' => $this->whenLoaded('transaction', fn () => $this->transaction?->rate),
            'transaction_id' => $this->whenLoaded('transaction', fn () => $this->transaction?->id),
            'transaction_status' => $this->whenLoaded('transaction', fn () => $this->transaction?->status),
            'category_allocations' => $this->whenLoaded('categoryAllocations', fn () => $this->categoryAllocations->map(fn ($row) => [
                'id' => $row->id,
                'landed_cost_category_id' => $row->landed_cost_category_id,
                'amount' => $row->amount,
                'category_name' => $row->category?->name,
            ])->values()),
            'category_allocations_total' => $this->whenLoaded('categoryAllocations', fn () => round((float) $this->categoryAllocations->sum('amount'), 2)),
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
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
            'created_at' => $this->created_at ? $dateConversionService->toDisplay($this->created_at) : null,
            'updated_at' => $this->updated_at ? $dateConversionService->toDisplay($this->updated_at) : null,
        ];
    }
}
