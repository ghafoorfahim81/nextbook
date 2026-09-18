<?php

namespace App\Http\Resources\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DiscountRuleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $dates = app(DateConversionService::class);

        return [
            'id' => $this->id,
            'name' => $this->name,

            // Raw values, for the edit modal to load back into its form.
            'scope' => $this->scope?->value,
            'scope_id' => $this->scope_id,
            'discount_type' => $this->discount_type?->value,
            'value' => $this->value,
            'customer_group_id' => $this->customer_group_id,
            'min_quantity' => $this->min_quantity,
            'starts_at' => $this->starts_at?->toDateString(),
            'ends_at' => $this->ends_at?->toDateString(),
            'is_priority' => (bool) $this->is_priority,
            'is_active' => (bool) $this->is_active,
            'show_on_invoice' => (bool) $this->show_on_invoice,

            // Ready-made labels, so the table renders plain strings.
            'scope_label' => $this->scope?->getLabel(),
            'target_name' => $this->targetLabel(),
            'value_label' => $this->discount_type === DiscountType::PERCENTAGE
                ? rtrim(rtrim(number_format((float) $this->value, 2, '.', ''), '0'), '.').'%'
                : number_format((float) $this->value, 2),
            // A rule with no group genuinely applies to everyone, so the table
            // says so rather than showing a dash that reads as missing data.
            'customer_group_name' => $this->customerGroup?->localized_name
                ?? __('discount_rule.all_customers'),
            'window_label' => $this->windowLabel($dates),
        ];
    }

    private function targetLabel(): string
    {
        if ($this->scope === DiscountScope::ALL) {
            return __('discount_rule.all_items');
        }

        $target = $this->resource->target();

        // Categories carry a localized label; items and brands only have `name`.
        return $target?->localized_name ?? $target?->name ?? '—';
    }

    private function windowLabel(DateConversionService $dates): string
    {
        if ($this->starts_at === null && $this->ends_at === null) {
            return __('discount_rule.always');
        }

        $from = $this->starts_at ? $dates->toDisplay($this->starts_at) : '…';
        $to = $this->ends_at ? $dates->toDisplay($this->ends_at) : '…';

        return "{$from} → {$to}";
    }
}
