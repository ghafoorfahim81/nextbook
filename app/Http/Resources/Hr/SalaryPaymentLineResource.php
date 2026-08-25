<?php

namespace App\Http\Resources\Hr;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryPaymentLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'payroll_line_id' => $this->payroll_line_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'payroll_number' => $this->whenLoaded(
                'payrollLine',
                fn () => $this->payrollLine?->payroll?->number
            ),
            'period_label' => $this->whenLoaded(
                'payrollLine',
                fn () => $this->payrollLine?->payroll?->period_label
            ),
            'amount' => (float) $this->amount,
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            // The rate the PAYSLIP was booked at, not the rate the cash moved
            // at — this line says what was relieved, and the difference
            // between the two rates is the FX the settlement posted separately.
            'rate' => (float) $this->rate,
        ];
    }
}
