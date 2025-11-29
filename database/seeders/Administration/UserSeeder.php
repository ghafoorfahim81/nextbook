<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
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
        $branch = Branch::where('name', 'Main Branch')->first();
        if (!$adminUser) {
            User::create([
                'id' => (string) new Ulid(),
                'name' => 'admin',
                'email' => 'admin@nextbook.com',
                'branch_id' => $branch->id,
                'password' => bcrypt('password'),
                'preferences' => User::DEFAULT_PREFERENCES,
            ]);
        }

    }
}
