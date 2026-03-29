<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
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
            $userData = [
                'id' => (string) new Ulid(),
                'name' => 'admin',
                'email' => 'admin@nextbook.com',
                'password' => bcrypt('password'),
                'preferences' => User::DEFAULT_PREFERENCES,
            ];

            if ($branch) {
                $userData['branch_id'] = $branch->id;
            }

            User::create($userData);
        } else if ($branch) {
            $adminUser->update([
                'branch_id' => $branch->id,
            ]);
        }
    }
}
