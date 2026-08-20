<?php

namespace App\Http\Resources\Hr;

use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class EmployeeLoanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);
        $status = $this->statusEnum();
        $type = $this->typeEnum();

        return [
            'id' => $this->id,
            'number' => $this->number,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'employee_code' => $this->whenLoaded('employee', fn () => $this->employee?->code),
            'loan_type' => $type->value,
            'loan_type_label' => $type->getLabel(),
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'rate' => (float) $this->rate,
            'principal_amount' => (float) $this->principal_amount,
            'installment_amount' => (float) $this->installment_amount,
            'installments_count' => (int) $this->installments_count,
            'deduct_from_payroll' => (bool) $this->deduct_from_payroll,
            'issue_date' => $dates->toDisplay($this->issue_date?->toDateString()),
            'first_deduction_period' => $dates->toDisplay($this->first_deduction_period?->toDateString()),
            'interest_rate' => (float) $this->interest_rate,
            'outstanding_amount' => (float) $this->outstanding_amount,
            'repaid_amount' => (float) $this->principal_amount - (float) $this->outstanding_amount,
            'status' => $status->value,
            'status_label' => $status->getLabel(),
            // Drives which buttons the UI offers, so the two cannot drift.
            'is_disbursed' => $status->isDisbursed(),
            'is_recoverable' => $status->isRecoverable(),
            'bank_account_id' => $this->bank_account_id,
            'transaction_id' => $this->transaction_id,
            'remark' => $this->remark,
            'repayments' => EmployeeLoanRepaymentResource::collection($this->whenLoaded('repayments')),
            'created_by' => $this->createdBy?->name,
        ];
    }
}
