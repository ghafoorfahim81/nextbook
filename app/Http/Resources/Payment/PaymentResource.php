<?php

namespace App\Http\Resources\Payment;

use App\Enums\PaymentMode;
use App\Http\Resources\Transaction\TransactionResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Http\Resources\Account\AccountResource;
use App\Http\Resources\UserManagement\UserSimpleResource;
use App\Http\Resources\Purchase\PurchasePaymentResource;

class PaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dateConversionService = app(\App\Services\DateConversionService::class);
        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $this->date ? $dateConversionService->toDisplay($this->date) : null,
            'ledger_id' => $this->ledger_id,
            'payment_mode' => $this->payment_mode instanceof PaymentMode
                ? $this->payment_mode->value
                : $this->payment_mode,
            'payment_mode_label' => $this->payment_mode instanceof PaymentMode
                ? $this->payment_mode->getLabel()
                : (PaymentMode::tryFrom((string) $this->payment_mode)?->getLabel() ?? $this->payment_mode),
            'ledger' => $this->whenLoaded('ledger'),
            'ledger_name' => $this->ledger?->name,
            // derive from bank/payment transactions
            'amount' => $this->transaction?->lines[0]->debit>0?$this->transaction?->lines[0]->debit: $this->transaction?->lines[0]->credit,
            'currency_id' => $this->transaction?->currency_id,
            'currency_code' => $this->transaction?->currency?->code,
            'rate' => $this->transaction?->rate,
            'bank_account_id' => $this->transaction?->lines[0]->account_id,
            'bank_account' => new AccountResource($this->transaction?->lines[0]->account),
            'cheque_no' => $this->cheque_no,
            'narration' => $this->narration,
            'description' => $this->narration,
            'transaction_id' => $this->transaction_id,
            'transaction' => new TransactionResource($this->transaction),
            'purchase_payments' => PurchasePaymentResource::collection($this->whenLoaded('purchasePayments')),
            'created_by' => UserSimpleResource::make($this->whenLoaded('createdBy')),
            'updated_by' => UserSimpleResource::make($this->whenLoaded('updatedBy')),
        ];
    }
}
