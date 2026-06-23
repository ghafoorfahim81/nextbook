<?php

namespace App\Http\Resources\Owner;

use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Administration\CurrencyResource;
use App\Http\Resources\Transaction\TransactionResource;
use App\Models\Account\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DrawingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $transaction = $this->relationLoaded('transaction') ? $this->transaction : null;
        $lines = $transaction?->lines ?? collect();

        if ($lines->isEmpty() && $transaction?->posting_payload) {
            $payloadLines = collect((array) data_get($transaction->posting_payload, 'lines', []));
            $creditPayload = $payloadLines->first(fn ($line) => (float) ($line['credit'] ?? 0) > 0);
            $debitPayload = $payloadLines->first(fn ($line) => (float) ($line['debit'] ?? 0) > 0);
            $amount = (float) data_get($transaction->posting_payload, 'amount', $creditPayload['credit'] ?? $debitPayload['debit'] ?? 0);
            $bankAccountId = data_get($transaction->posting_payload, 'bank_account_id', $creditPayload['account_id'] ?? null);
            $drawingAccountId = data_get($transaction->posting_payload, 'drawing_account_id', $debitPayload['account_id'] ?? null);
            $bankAccount = $bankAccountId ? Account::find($bankAccountId) : null;
            $drawingAccount = $drawingAccountId ? Account::find($drawingAccountId) : null;
        } else {
            $creditLine = $lines->first(fn ($line) => (float) ($line->credit ?? 0) > 0);
            $debitLine = $lines->first(fn ($line) => (float) ($line->debit ?? 0) > 0);
            $amount = $creditLine
                ? ((float) $creditLine->credit > 0 ? $creditLine->credit : $creditLine->debit)
                : ($debitLine ? ($debitLine->debit ?: $debitLine->credit) : 0);
            $bankAccountId = $creditLine?->account_id;
            $drawingAccountId = $debitLine?->account_id;
            $bankAccount = $creditLine?->relationLoaded('account') ? $creditLine->account : null;
            $drawingAccount = $debitLine?->relationLoaded('account') ? $debitLine->account : null;
        }

        return [
            'id' => $this->id,
            'number' => $this->number,
            'owner_id' => $this->owner_id,
            'owner' => OwnerResource::make($this->whenLoaded('owner')),
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'narration' => $this->narration,
            'amount' => $amount,
            'currency_id' => $transaction?->currency_id,
            'currency' => $transaction?->relationLoaded('currency') && $transaction->currency
                ? CurrencyResource::make($transaction->currency)
                : null,
            'rate' => $transaction?->rate,
            'bank_account_id' => $bankAccountId ?? null,
            'bank_account' => $bankAccount ? new AccountResource($bankAccount) : null,
            'drawing_account_id' => $drawingAccountId ?? null,
            'drawing_account' => $drawingAccount ? new AccountResource($drawingAccount) : null,
            'transaction_id' => $transaction?->id,
            'transaction' => $transaction ? new TransactionResource($transaction) : null,
            'status' => $transaction?->status,
            'branch_id' => $this->branch_id,
            'created_by' => $this->createdBy,
            'updated_by' => $this->updatedBy,
        ];
    }
}
