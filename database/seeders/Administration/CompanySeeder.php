<?php

namespace Database\Seeders;

use App\Models\Administration\Company;
use Illuminate\Database\Seeder;
use App\Enums\CalendarType;
use App\Enums\BusinessType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;

class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $company = Company::create([
            'name_en' => 'NextBook',
            'name_fa' => 'نکست بوک همراه',
            'name_pa' => 'نکست بوک همراه',
            'abbreviation' => 'NB',
            'address' => '123 Main St, Anytown, USA',
            'phone' => '03123456789',
            'country' => 'Pakistan',
            'city' => 'Karachi',
            'logo' => 'logo.png',
            'calendar_type' => CalendarType::Gregorian,
            'locale' => Locale::English,
            'working_style' => WorkingStyle::pharmacy_shop,
            'business_type' => BusinessType::pharmacy_shop,
        ]);
    }
}
