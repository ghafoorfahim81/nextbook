<?php

namespace App\Http\Resources\Hr;

use App\Enums\PayrollLinePaymentStatus;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A payslip.
 */
class PayrollLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $status = $this->payment_status instanceof PayrollLinePaymentStatus
            ? $this->payment_status
            : PayrollLinePaymentStatus::tryFrom((string) $this->payment_status);

        return [
            'id' => $this->id,
            'payroll_id' => $this->payroll_id,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'employee_code' => $this->whenLoaded('employee', fn () => $this->employee?->code),
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'rate' => (float) $this->rate,
            'working_days' => (float) $this->working_days,
            'present_days' => (float) $this->present_days,
            'absent_days' => (float) $this->absent_days,
            'paid_leave_days' => (float) $this->paid_leave_days,
            'unpaid_leave_days' => (float) $this->unpaid_leave_days,
            'overtime_hours' => (float) $this->overtime_hours,
            'basic_salary' => (float) $this->basic_salary,
            'gross_earnings' => (float) $this->gross_earnings,
            'total_deductions' => (float) $this->total_deductions,
            'taxable_income' => (float) $this->taxable_income,
            'tax_amount' => (float) $this->tax_amount,
            'net_payable' => (float) $this->net_payable,
            'paid_amount' => (float) $this->paid_amount,
            'outstanding' => $this->outstanding(),
            'payment_status' => $status?->value,
            'payment_status_label' => $status?->getLabel(),
            'tax_bracket_set_id' => $this->tax_bracket_set_id,
            'tax_table_name' => $this->whenLoaded('taxBracketSet', fn () => $this->taxBracketSet?->name),
            'remark' => $this->remark,
            'components' => PayrollLineComponentResource::collection($this->whenLoaded('components')),
        ];
    }
}
