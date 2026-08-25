<?php

namespace App\Http\Resources\Hr;

use App\Enums\PayFrequency;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryStructureResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $frequency = $this->pay_frequency instanceof PayFrequency
            ? $this->pay_frequency
            : PayFrequency::tryFrom((string) $this->pay_frequency);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'employee_id' => $this->employee_id,
            'employee_name' => $this->whenLoaded('employee', fn () => $this->employee?->full_name),
            'employee_code' => $this->whenLoaded('employee', fn () => $this->employee?->code),
            'designation_id' => $this->designation_id,
            'designation_name' => $this->whenLoaded('designation', fn () => $this->designation?->name),
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn () => $this->department?->name),
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'effective_from' => $dates->toDisplay($this->effective_from?->toDateString()),
            'effective_to' => $dates->toDisplay($this->effective_to?->toDateString()),
            'basic_salary' => (float) $this->basic_salary,
            'pay_frequency' => $frequency?->value,
            'pay_frequency_label' => $frequency?->getLabel(),
            'expense_account_id' => $this->expense_account_id,
            'is_active' => (bool) $this->is_active,
            'remark' => $this->remark,
            'lines' => SalaryStructureLineResource::collection($this->whenLoaded('lines')),
            'created_by' => $this->createdBy?->name,
        ];
    }
}
