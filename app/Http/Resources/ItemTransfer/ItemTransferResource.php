<?php

namespace App\Http\Resources\ItemTransfer;

use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Http\Resources\AttachmentResource;
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
            'from_warehouse_id' => $this->from_warehouse_id,
            'from_warehouse' => $this->whenLoaded('fromWarehouse', fn() => [
                'id' => $this->fromWarehouse->id,
                'name' => $this->fromWarehouse->name,
            ]),
            'to_warehouse_id' => $this->to_warehouse_id,
            'to_warehouse' => $this->whenLoaded('toWarehouse', fn() => [
                'id' => $this->toWarehouse->id,
                'name' => $this->toWarehouse->name,
            ]),
            'status' => $this->status->value,
            'status_label' => $this->status->getLabel(),
            'has_transfer_cost' => (bool) $this->has_transfer_cost,
            'transfer_cost' => $this->transfer_cost,
            'bank_account_id' => $this->bank_account_id,
            'bank_account' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount ? [
                'id' => $this->bankAccount->id,
                'name' => $this->bankAccount->name,
                'local_name' => $this->bankAccount->local_name,
            ] : null),
            'expense_account_id' => $this->expense_account_id,
            'expense_account' => $this->whenLoaded('expenseAccount', fn () => $this->expenseAccount ? [
                'id' => $this->expenseAccount->id,
                'name' => $this->expenseAccount->name,
                'local_name' => $this->expenseAccount->local_name,
            ] : null),
            'currency_id' => $this->currency_id,
            'currency' => $this->whenLoaded('currency', fn () => $this->currency ? [
                'id' => $this->currency->id,
                'name' => $this->currency->name,
                'code' => $this->currency->code,
                'symbol' => $this->currency->symbol,
            ] : null),
            'rate' => $this->rate,
            'branch_id' => $this->branch_id,
            'branch' => $this->whenLoaded('branch'),
            'remarks' => $this->remarks,
            'items' => ItemTransferItemResource::collection($this->whenLoaded('items')),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
