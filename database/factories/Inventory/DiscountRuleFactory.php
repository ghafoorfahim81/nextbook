<?php

namespace Database\Factories\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Inventory\DiscountRule;
use Illuminate\Database\Eloquent\Factories\Factory;

class DiscountRuleFactory extends Factory
{
    protected $model = DiscountRule::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'scope' => DiscountScope::ITEM->value,
            'scope_id' => null,
            'discount_type' => DiscountType::PERCENTAGE->value,
            'value' => 10,
            'customer_group_id' => null,
            'min_quantity' => null,
            'starts_at' => null,
            'ends_at' => null,
            'priority' => 0,
            'is_active' => true,
            'show_on_invoice' => true,
        ];
    }
}
