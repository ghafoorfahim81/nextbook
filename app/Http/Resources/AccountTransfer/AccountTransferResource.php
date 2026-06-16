<?php

namespace App\Http\Resources\AccountTransfer;

use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserManagement\UserSimpleResource;

class AccountTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $fromAccount = $this->fromAccount;
        $toAccount = $this->toAccount;
        $locale = app()->getLocale();
        $displayName = fn($acc) => $acc
            ? ($locale === 'en' ? $acc->name : ($acc->local_name ?? $acc->name))
            : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'status' => $this->status ?? $this->transaction?->status,
            'remark' => $this->remark,
            'transaction' => new TransactionResource($this->whenLoaded('transaction')),
            'from_account_name' => $displayName($fromAccount),
            'to_account_name' => $displayName($toAccount),
            'from_account_id' => $this->from_account_id,
            'to_account_id' => $this->to_account_id,
            'from_account' => $fromAccount ? ['id' => $fromAccount->id, 'name' => $displayName($fromAccount)] : null,
            'to_account' => $toAccount ? ['id' => $toAccount->id, 'name' => $displayName($toAccount)] : null,
            'amount' => $this->transaction?->lines?->first()
                ? ((float) $this->transaction->lines->first()->debit > 0 ? $this->transaction->lines->first()->debit : $this->transaction->lines->first()->credit)
                : (float) data_get($this->transaction?->posting_payload, 'amount', 0),
            'currency_code' => $this->transaction?->currency?->code,
            'currency_id' => $this->transaction?->currency?->id,
            'rate' => $this->transaction?->rate,
            'currency' => ($this->transaction?->currency),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
