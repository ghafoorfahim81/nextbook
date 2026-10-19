<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Company;
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
            $exists = Size::withoutGlobalScopes()
                ->where('code', $size['code'])
                ->exists();
            if ($exists) {
                continue;
            }
            Size::create([
                'name' => $size['name'],
                'code' => $size['code'],
                'company_id' => Company::withoutGlobalScopes()->value('id'),
                'is_main' => true,
                'is_active' => false,
            ]);
        }
    }
}
