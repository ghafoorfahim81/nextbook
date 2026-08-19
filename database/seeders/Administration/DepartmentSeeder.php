<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Department;
use App\Models\User;
use Illuminate\Database\Seeder;
use Symfony\Component\Uid\Ulid;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $exists = Department::withoutGlobalScopes()
            ->where('name', 'Main Department')
            ->exists();
        if ($exists) {
            return;
        }
        Department::create([
            'id'  => Ulid::generate(),
            'name' => 'Main Department',
            'code' => 'DEP-1',
            'remark' => 'This is default Department',
        ]);
    }
}
