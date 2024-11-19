<?php

namespace Database\Seeders\ControlPanel;

use App\Models\ControlPanel\Designation;
use Illuminate\Database\Seeder;

class DesignationSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Designation::factory()->count(5)->create();
    }
}
