<?php

namespace Database\Factories\Administration;

use App\Models\Administration\CustomerGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

class CustomerGroupFactory extends Factory
{
    protected $model = CustomerGroup::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(2, true);

        return [
            'name_en' => $name,
            'name_fa' => $name,
            'description' => null,
        ];
    }
}
