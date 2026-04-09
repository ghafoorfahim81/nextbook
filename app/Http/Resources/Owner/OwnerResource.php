<?php

namespace App\Http\Resources\Owner;

use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\Administration\CurrencyResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class OwnerResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        // $transaction = $this->relationLoaded('transaction') ? $this->transaction : null;
        // $lines = $transaction?->lines ?? collect();
        // $debitLine = $lines->first(fn ($line) => (float) ($line->debit ?? 0) > 0);
        // $creditLine = $lines->first(fn ($line) => (float) ($line->credit ?? 0) > 0);

        // $buildTransactionPayload = function ($line) use ($transaction) {
        //     if (! $transaction) {
        //         return null;
        //     }

        //     return [
        //         'id' => $transaction->id,
        //         'voucher_number' => $transaction->voucher_number,
        //         'status' => $transaction->status,
        //         'reference_type' => $transaction->reference_type,
        //         'reference_id' => $transaction->reference_id,
        //         'currency_id' => $transaction->currency_id,
        //         'currency' => $transaction->relationLoaded('currency')
        //             ? new CurrencyResource($transaction->currency)
        //             : null,
        //         'rate' => $transaction->rate,
        //         'remark' => $transaction->remark,
        //         'amount' => $line ? ((float) $line->debit > 0 ? $line->debit : $line->credit) : null,
        //         'account_id' => $line?->account_id,
        //         'account' => $line?->relationLoaded('account') && $line->account
        //             ? new AccountResource($line->account)
        //             : null,
        //     ];
        // };

        return [
            'id' => $this->id,
            'name' => $this->name,
            'father_name' => $this->father_name,
            'nic' => $this->nic,
            'email' => $this->email,
            'address' => $this->address,
            'phone_number' => $this->phone_number,
            'share_percentage' => $this->share_percentage,
            'ownership_percentage' => $this->share_percentage,
            'is_active' => $this->is_active, 
            'capital_account_id' => $this->capital_account_id,
            'drawing_account_id' => $this->drawing_account_id,
            'capital_account' => $this->whenLoaded('capitalAccount'),
            'drawing_account' => $this->whenLoaded('drawingAccount'),
            'bank_account_transaction' => $this->transaction?->lines?->first(),
            'capital_account_transaction' => $this->transaction?->lines?->last(),
            'amount' => $this->transaction?->lines?->first()?->debit ?? $this->transaction?->lines?->first()?->credit,
            'rate' => $this->transaction?->rate,
            'opening_currency_id' => $this->transaction?->currency_id,
            'opening_currency' => $this->transaction?->currency,
            'bank_account_id' => $this->transaction?->lines?->first()?->account_id, 
            'bank_account' => $this->transaction?->lines?->first()?->account,
        ];
    }
}
