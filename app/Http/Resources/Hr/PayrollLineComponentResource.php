<?php

namespace App\Http\Resources\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\SalaryComponentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One line on a payslip.
 *
 * Reads the SNAPSHOTTED name and code on the row, not the live component:
 * a payslip reprinted two years later must show what was paid, under the name
 * it was paid under — not what the component happens to be called today, or
 * that it has since been deleted.
 */
class PayrollLineComponentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $type = $this->component_type instanceof SalaryComponentType
            ? $this->component_type
            : SalaryComponentType::tryFrom((string) $this->component_type);

        $calculation = $this->calculation_type instanceof ComponentCalculationType
            ? $this->calculation_type
            : ComponentCalculationType::tryFrom((string) $this->calculation_type);

        return [
            'id' => $this->id,
            'salary_component_id' => $this->salary_component_id,
            'component_code' => $this->component_code,
            'component_name' => $this->component_name,
            'component_type' => $type?->value,
            'component_type_label' => $type?->getLabel(),
            'calculation_type' => $calculation?->value,
            'calculation_type_label' => $calculation?->getLabel(),
            'rate_or_percentage' => $this->rate_or_percentage !== null
                ? (float) $this->rate_or_percentage
                : null,
            'base_amount' => $this->base_amount !== null ? (float) $this->base_amount : null,
            'amount' => (float) $this->amount,
            'is_taxable' => (bool) $this->is_taxable,
            'sequence' => (int) $this->sequence,
        ];
    }
}
