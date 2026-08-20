<?php

namespace App\Http\Resources\Hr;

use App\Enums\PaymentMode;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryPaymentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $mode = $this->payment_mode instanceof PaymentMode
            ? $this->payment_mode
            : PaymentMode::tryFrom((string) $this->payment_mode);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'date' => $dates->toDisplay($this->date?->toDateString()),
            'payroll_id' => $this->payroll_id,
            'payroll_number' => $this->whenLoaded('payroll', fn () => $this->payroll?->number),
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'employee_code' => $this->whenLoaded('employee', fn () => $this->employee?->code),
            'ledger_id' => $this->ledger_id,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'rate' => (float) $this->rate,
            'amount' => (float) $this->amount,
            'payment_mode' => $mode?->value,
            'payment_mode_label' => $mode?->getLabel(),
            'bank_account_id' => $this->bank_account_id,
            'bank_account_name' => $this->whenLoaded('bankAccount', fn () => $this->bankAccount?->name),
            'cheque_no' => $this->cheque_no,
            'transaction_id' => $this->transaction_id,
            'narration' => $this->narration,
            'lines' => SalaryPaymentLineResource::collection($this->whenLoaded('lines')),
            'created_by' => $this->createdBy?->name,
        ];
    }
}
