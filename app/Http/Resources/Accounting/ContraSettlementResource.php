<?php

namespace App\Http\Resources\Accounting;

use App\Enums\TransactionStatus;
use App\Http\Resources\UserManagement\UserSimpleResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContraSettlementResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(\App\Services\DateConversionService::class);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dates->toDisplay($this->date) : null,
            'status' => $this->status,
            'status_label' => TransactionStatus::tryFrom((string) $this->status)?->getLabel() ?? (string) $this->status,
            'customer_ledger_id' => $this->customer_ledger_id,
            'customer_ledger_name' => $this->customerLedger?->name,
            'supplier_ledger_id' => $this->supplier_ledger_id,
            'supplier_ledger_name' => $this->supplierLedger?->name,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->currency?->code,
            'rate' => $this->rate,
            'amount' => (float) $this->amount,
            'narration' => $this->narration,
            'customer_transaction_id' => $this->customer_transaction_id,
            'supplier_transaction_id' => $this->supplier_transaction_id,
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
