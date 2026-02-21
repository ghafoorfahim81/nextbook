<?php

namespace Database\Factories\JournalEntry;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\JournalEntry\JournalType;
use App\Models\User;

class JournalTypeFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = JournalType::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'description' => fake()->text(),
            'created_by' => User::factory()->create()->created_by,
            'updated_by' => User::factory()->create()->updated_by,
            'deleted_by' => User::factory()->create()->deleted_by,
        ];
    }
}
