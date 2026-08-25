<?php

namespace App\Http\Resources\Hr;

use App\Enums\TaxPeriod;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TaxBracketSetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $period = $this->period instanceof TaxPeriod
            ? $this->period
            : TaxPeriod::tryFrom((string) $this->period);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'jurisdiction' => $this->jurisdiction,
            'period' => $period?->value,
            'period_label' => $period?->getLabel(),
            'effective_from' => $dates->toDisplay($this->effective_from?->toDateString()),
            'effective_to' => $dates->toDisplay($this->effective_to?->toDateString()),
            'currency_id' => $this->currency_id,
            'currency_code' => $this->whenLoaded('currency', fn () => $this->currency?->code),
            'is_active' => (bool) $this->is_active,
            // Marks the table shipped with the system. Editable like any
            // other — the defaults are a starting point, not a rule — but
            // worth flagging so a user knows what they are changing.
            'is_system' => (bool) $this->is_system,
            'remark' => $this->remark,
            'brackets' => TaxBracketResource::collection($this->whenLoaded('brackets')),
            'created_by' => $this->createdBy?->name,
        ];
    }
}
