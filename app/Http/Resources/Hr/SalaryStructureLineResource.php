<?php

namespace App\Http\Resources\Hr;

use App\Enums\ComponentCalculationType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class SalaryStructureLineResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $calculation = $this->calculation_type instanceof ComponentCalculationType
            ? $this->calculation_type
            : ComponentCalculationType::tryFrom((string) $this->calculation_type);

        return [
            'id' => $this->id,
            'salary_component_id' => $this->salary_component_id,
            'component_name' => $this->whenLoaded('component', fn () => $this->component?->name),
            'component_code' => $this->whenLoaded('component', fn () => $this->component?->code),
            'component_type' => $this->whenLoaded(
                'component',
                fn () => $this->component?->component_type?->value
            ),
            'calculation_type' => $calculation?->value,
            'calculation_type_label' => $calculation?->getLabel(),
            'amount' => $this->amount !== null ? (float) $this->amount : null,
            'percentage' => $this->percentage !== null ? (float) $this->percentage : null,
            'sequence' => (int) $this->sequence,
        ];
    }
}
