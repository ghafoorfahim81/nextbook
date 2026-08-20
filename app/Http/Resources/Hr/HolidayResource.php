<?php

namespace App\Http\Resources\Hr;

use App\Enums\HolidayType;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class HolidayResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        $type = $this->holiday_type instanceof HolidayType
            ? $this->holiday_type
            : HolidayType::tryFrom((string) $this->holiday_type);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'date' => $dates->toDisplay($this->date),
            'end_date' => $dates->toDisplay($this->end_date),
            'holiday_type' => $type?->value,
            'holiday_type_label' => $type?->getLabel(),
            'is_recurring' => (bool) $this->is_recurring,
            'is_paid' => (bool) $this->is_paid,
            'day_count' => count($this->coveredDates()),
            'remark' => $this->remark,
            'created_by' => $this->createdBy?->name,
        ];
    }
}
