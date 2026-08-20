<?php

namespace App\Http\Resources\Hr;

use App\Enums\ComponentCalculationType;
use App\Enums\SalaryComponentType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryComponentResource extends JsonResource
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
            'name' => $this->name,
            'code' => $this->code,
            'component_type' => $type?->value,
            'component_type_label' => $type?->getLabel(),
            'calculation_type' => $calculation?->value,
            'calculation_type_label' => $calculation?->getLabel(),
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'percentage' => $this->percentage !== null ? (float) $this->percentage : null,
            'is_taxable' => (bool) $this->is_taxable,
            'affects_gross' => (bool) $this->affects_gross,
            'is_prorated' => (bool) $this->is_prorated,
            'account_id' => $this->account_id,
            'account_name' => $this->whenLoaded('account', fn () => $this->account?->name),
            'sequence' => (int) $this->sequence,
            // Surfaced so the UI can grey out the rows payroll depends on:
            // deleting BASIC or WITHHOLDING_TAX would break every run.
            'is_system' => (bool) $this->is_system,
            'is_active' => (bool) $this->is_active,
            'created_by' => $this->createdBy?->name,
        ];
    }
}
