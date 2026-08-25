<?php

namespace App\Http\Resources\Hr;

use App\Enums\PayFrequency;
use App\Enums\PayrollStatus;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PayrollResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);
        $status = $this->statusEnum();

        $frequency = $this->pay_frequency instanceof PayFrequency
            ? $this->pay_frequency
            : PayFrequency::tryFrom((string) $this->pay_frequency);

        return [
            'id' => $this->id,
            'number' => $this->number,
            'name' => $this->name,
            'period_start' => $dates->toDisplay($this->period_start?->toDateString()),
            'period_end' => $dates->toDisplay($this->period_end?->toDateString()),
            'pay_date' => $dates->toDisplay($this->pay_date?->toDateString()),
            // Already Jalali, denormalised at creation — do NOT run it through
            // the date converter, it is a label rather than a date.
            'period_label' => $this->period_label,
            'pay_frequency' => $frequency?->value,
            'pay_frequency_label' => $frequency?->getLabel(),
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'rate' => (float) $this->rate,
            'status' => $status->value,
            'status_label' => $status->getLabel(),
            // The UI offers exactly these buttons, so a state machine change
            // never leaves a dead button behind.
            'allowed_transitions' => $status->allowedTransitions(),
            'is_posted' => $status->isPosted(),
            'is_recalculable' => $status->isRecalculable(),
            'total_gross' => (float) $this->total_gross,
            'total_deductions' => (float) $this->total_deductions,
            'total_tax' => (float) $this->total_tax,
            'total_net' => (float) $this->total_net,
            'employee_count' => (int) $this->employee_count,
            'department_id' => $this->department_id,
            'department_name' => $this->whenLoaded('department', fn () => $this->department?->name),
            'employment_type' => $this->employment_type,
            'transaction_id' => $this->transaction_id,
            'reversal_transaction_id' => $this->reversal_transaction_id,
            'approved_by' => $this->approver?->name,
            'approved_at' => $dates->toDisplay($this->approved_at?->toDateString()),
            'posted_by' => $this->poster?->name,
            'posted_at' => $dates->toDisplay($this->posted_at?->toDateString()),
            'cancellation_reason' => $this->cancellation_reason,
            'remark' => $this->remark,
            'lines' => PayrollLineResource::collection($this->whenLoaded('lines')),
            'created_by' => $this->createdBy?->name,
        ];
    }
}
