<?php

namespace Database\Seeders;

use App\Models\Administration\Department;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::create([
            'id' => Str::uuid(),
            'name' => 'admin',
            'email' => 'admin@gmail.com',
            'password' => bcrypt('password'),

        ]);

        Department::factory()->count(5)->create();
    }
}
