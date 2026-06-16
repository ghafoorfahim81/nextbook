<?php

namespace App\Http\Resources\AccountTransfer;

use App\Http\Resources\Transaction\TransactionResource;
use App\Models\Account\Account;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\UserManagement\UserSimpleResource;

class AccountTransferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        ['from' => $fromAccount, 'to' => $toAccount] = $this->resolveAccounts();
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
            'from_account_id' => $fromAccount?->id,
            'to_account_id' => $toAccount?->id,
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

    private function resolveAccounts(): array
    {
        $lines = $this->transaction?->lines;

        // Posted: actual TransactionLine records exist
        if ($lines && $lines->isNotEmpty()) {
            $firstAccount = $lines->first()?->account;
            $nature = $firstAccount?->accountType?->nature;
            // debit line is toAccount for asset/expense, fromAccount for liability/equity/income
            if ($nature === 'asset' || $nature === 'expense') {
                return ['from' => $lines->last()?->account, 'to' => $firstAccount];
            }
            return ['from' => $firstAccount, 'to' => $lines->last()?->account];
        }

        // Draft: lines not created yet; account IDs live in posting_payload.lines
        $payloadLines = $this->transaction?->posting_payload['lines'] ?? [];
        if (count($payloadLines) >= 2) {
            $debitAccountId  = $payloadLines[0]['account_id'] ?? null;
            $creditAccountId = $payloadLines[1]['account_id'] ?? null;
            $debitAccount    = $debitAccountId  ? Account::find($debitAccountId)  : null;
            $creditAccount   = $creditAccountId ? Account::find($creditAccountId) : null;
            $nature = $debitAccount?->accountType?->nature;
            if ($nature === 'asset' || $nature === 'expense') {
                return ['from' => $creditAccount, 'to' => $debitAccount];
            }
            return ['from' => $debitAccount, 'to' => $creditAccount];
        }

        return ['from' => null, 'to' => null];
    }
}
