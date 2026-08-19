<?php

namespace App\Http\Resources\Payment;

use App\Enums\PaymentMode;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Http\Resources\AttachmentResource;
use App\Http\Resources\Accounting\SettlementResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $locale = app()->getLocale();
        $firstLine = $this->transaction?->lines?->where('debit', '>', 0)->first();
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'ledger_id' => $this->ledger_id,
            'status' => $this->status ?? $this->transaction?->status,
            'payment_mode' => $this->payment_mode instanceof PaymentMode
                ? $this->payment_mode->value
                : $this->payment_mode,
            'payment_mode_label' => PaymentMode::labelFor($this->payment_mode),
            'ledger' => $this->whenLoaded('ledger'),
            'ledger_name' => $this->ledger?->name,
            // The cash line, never lines[0] — a settlement voucher carries the
            // payable relief and any exchange difference alongside the cash.
            'amount' => $this->paidAmount(),
            'currency_id' => $this->transaction?->currency_id,
            'currency_code' => $this->transaction?->currency?->code,
            'rate' => $this->transaction?->rate,
            'bank_account' => new AccountResource($this->bankAccount()),
            'bank_account_id' => $this->bankAccount()?->id ?? null,
            'bank_account_name' => $locale === 'en' ? $this->bankAccount()?->name : $this->bankAccount()?->local_name ?? null,
            'cheque_no' => $this->cheque_no,
            'narration' => $this->narration,
            'description' => $this->narration,
            // Payments have no transaction_id column — the voucher points back
            // at the payment. The edit form needs the real id so it can exclude
            // this voucher's own settlements from "already settled".
            'transaction_id' => $this->transaction?->id,
            'transaction' => new TransactionResource($this->transaction),
            // The payable mirror of the receipt: what this payment relieved and
            // at which rates. purchase_payments never stored any of this.
            'settlements' => SettlementResource::collection($this->whenLoaded('settlements')),
            'is_cross_currency' => (bool) ($this->transaction?->is_cross_currency ?? false),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
            'attachments' => AttachmentResource::collection($this->whenLoaded('attachments')),
        ];
    }
}
