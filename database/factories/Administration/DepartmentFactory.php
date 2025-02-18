<?php

namespace Database\Factories\Administration;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;
use App\Models\Administration\Department;
use App\Models\User;

class DepartmentFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Department::class;

    /**
     * Define the model's default state.
     */
    public function definition(): array
    {
        $user = User::where('name', 'admin')->first();

        // If the admin user doesn't exist, create it

//    dd($user->name);
    \Log::info($user);
        return [
            'id' => Str::uuid(), // Generate a UUID for the department ID
            'name' => $this->faker->name(),
            'remark' => $this->faker->text(),
            'created_by' => $user['id'] // Use the UUID of the admin user
        ];
    }
}
