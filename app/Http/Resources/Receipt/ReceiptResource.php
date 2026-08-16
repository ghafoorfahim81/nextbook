<?php

namespace App\Http\Resources\Receipt;

use App\Enums\PaymentMode;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Http\Resources\Accounting\SettlementResource;
class ReceiptResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     */
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        $locale = app()->getLocale();
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'ledger_id' => $this->ledger_id,
            'payment_mode' => $this->payment_mode instanceof PaymentMode
                ? $this->payment_mode->value
                : $this->payment_mode,
            'payment_mode_label' => PaymentMode::labelFor($this->payment_mode),
            'ledger' => $this->whenLoaded('ledger'),
            'ledger_name' => $this->ledger?->name,
            // The cash line, never lines[0]. A settlement voucher carries the
            // receivable relief and any exchange difference alongside the cash,
            // and their order is not guaranteed — reading the first line put a
            // receivable amount into the edit form's Amount box.
            'amount' => $this->receivedAmount(),
            'currency_id' => $this->transaction?->currency_id,
            'currency_code' => $this->transaction?->currency?->code,
            'rate' => $this->transaction?->rate,
            'cheque_no' => $this->cheque_no,
            'narration' => $this->narration,
            // Receipts have no transaction_id column — the voucher points back
            // at the receipt. The edit form needs the real id so it can exclude
            // this voucher's own settlements from "already settled".
            'transaction_id' => $this->transaction?->id,
            'bank_account_id' => $this->bankAccount()?->id ?? null,
            'bank_account_name' => $locale === 'en' ? $this->bankAccount()?->name : $this->bankAccount()?->local_name ?? null,
            'bank_account' => new AccountResource($this->bankAccount()),
            'transaction' => new TransactionResource($this->transaction),
            // What this receipt actually relieved, and at which rates. The old
            // sale_receives shape is gone: allocation lives in settlements only,
            // so there is one answer to "how much is left on this invoice".
            'settlements' => SettlementResource::collection($this->whenLoaded('settlements')),
            'is_cross_currency' => (bool) ($this->transaction?->is_cross_currency ?? false),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}

