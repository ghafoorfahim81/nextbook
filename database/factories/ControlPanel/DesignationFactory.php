<?php

namespace Database\Factories\ControlPanel;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\ControlPanel\Designation;
use App\Models\User;

class DesignationFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Designation::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'id' => Str::uuid()->toString(),
            'name' => $this->faker->name(),
            'remark' => $this->faker->text(),
            'created_by' => User::factory(),
            'updated_by' => User::factory(),
            'deleted_by' => User::factory(),
        ];
    }
}
