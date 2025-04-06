<?php

namespace Database\Seeders;

use App\Models\Administration\Branch;
use App\Models\Administration\Category;
use App\Models\Administration\Department;
use App\Models\User;
use Database\Seeders\Administration\BranchSeeder;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Database\Seeders\Administration\CategorySeeder;
use Database\Seeders\Administration\DepartmentSeeder;
use Database\Seeders\Administration\UserSeeder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
//        $x = User::create([
//            'id' => Str::uuid(),
//            'name' => 'admin',
//            'email' => 'admin@gmail.com',
//            'password' => bcrypt('password'),
//        ]);
        // dd($x);
        $this->call(UserSeeder::class);

        $this->call(CategorySeeder::class);
        $this->call(BranchSeeder::class);

        Branch::factory()->count(20)->create();
//        Category::factory()->count(20)->create();
    }
}
