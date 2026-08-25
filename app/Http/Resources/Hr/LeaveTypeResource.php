<?php

namespace App\Http\Resources\Hr;

use App\Enums\Gender;
use App\Enums\LeaveAccrualMethod;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class LeaveTypeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $method = $this->accrual_method instanceof LeaveAccrualMethod
            ? $this->accrual_method
            : LeaveAccrualMethod::tryFrom((string) $this->accrual_method);

        $gender = $this->applicable_gender instanceof Gender
            ? $this->applicable_gender
            : Gender::tryFrom((string) $this->applicable_gender);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'colour' => $this->colour,
            'is_paid' => (bool) $this->is_paid,
            'accrual_method' => $method?->value,
            'accrual_method_label' => $method?->getLabel(),
            'days_per_year' => $this->days_per_year !== null ? (float) $this->days_per_year : null,
            'accrual_rate_per_month' => $this->accrual_rate_per_month !== null ? (float) $this->accrual_rate_per_month : null,
            'max_carry_forward_days' => $this->max_carry_forward_days !== null ? (float) $this->max_carry_forward_days : null,
            'carry_forward_expiry_months' => $this->carry_forward_expiry_months,
            'max_consecutive_days' => $this->max_consecutive_days,
            'min_notice_days' => $this->min_notice_days,
            'min_service_months' => $this->min_service_months,
            'applicable_gender' => $gender?->value,
            'applicable_gender_label' => $gender?->getLabel(),
            'requires_attachment' => (bool) $this->requires_attachment,
            'requires_approval' => (bool) $this->requires_approval,
            'deduct_from_salary' => (bool) $this->deduct_from_salary,
            'is_encashable' => (bool) $this->is_encashable,
            'pro_rata_on_join' => (bool) $this->pro_rata_on_join,
            'excludes_holidays' => (bool) $this->excludes_holidays,
            'excludes_weekends' => (bool) $this->excludes_weekends,
            'is_active' => (bool) $this->is_active,
            'remark' => $this->remark,
            'created_by' => $this->createdBy?->name,
        ];
    }
}
