<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Symfony\Component\Uid\Ulid;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $adminUser = User::where('name', 'admin')->first();

        // If the admin user doesn't exist, create it
        if (!$adminUser) {
            User::create([
                'id' => (string) new Ulid(),
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('password'),
            ]);
        }

    }
}
