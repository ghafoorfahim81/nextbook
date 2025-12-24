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

        $sizes = [
            ['name' => 'خورد', 'code' => 'SM'],
            ['name' => 'متوسط', 'code' => 'MD'],
            ['name' => 'کلان', 'code' => 'LG'],
            ['name' => 'Small', 'code' => 'S'],
            ['name' => 'Medium', 'code' => 'M'],
            ['name' => 'Large', 'code' => 'L'],
            ['name' => 'X-Large', 'code' => 'XL'],
            ['name' => 'XL', 'code' => 'X1'],
            ['name' => 'XS', 'code' => 'XS'],
            ['name' => 'M', 'code' => 'M1'],
            ['name' => 'L', 'code' => 'L1'],
            ['name' => 'XXL', 'code' => 'XXL'],
            ['name' => 'A6', 'code' => 'A6'],
            ['name' => 'A5', 'code' => 'A5'],
            ['name' => 'A4', 'code' => 'A4'],
            ['name' => 'A3', 'code' => 'A3'],
            ['name' => 'A2', 'code' => 'A2'],
            ['name' => 'N_A', 'code' => 'NA']
        ];
        foreach ($sizes as $size) {
            Size::create($size);
        }
    }
}
