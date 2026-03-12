<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Size;
use Illuminate\Database\Seeder;

class SizeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {


        $sizes = Size::defaultSizes();
        foreach ($sizes as $size) {
            Size::create([
                'name' => $size['name'],
                'code' => $size['code'],
                'branch_id' => Branch::where('is_main', true)->first()->id,
                'is_main' => true,
                'is_active' => false,
            ]);
        }
    }
}
