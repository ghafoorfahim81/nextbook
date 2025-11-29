<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Company;
use Illuminate\Database\Seeder;
use App\Enums\CalendarType;
use App\Enums\BusinessType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use Symfony\Component\Uid\Ulid;
use App\Models\Administration\Currency;
class CompanySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    { 
        $company = Company::create([
            'id' => (string) new Ulid(),
            'name_en' => 'NextBook',
            'name_fa' => 'نکست بوک همراه',
            'name_pa' => 'نکست بوک همراه',
            'abbreviation' => 'NB',
            'address' => '123 Main St, Anytown, USA',
            'phone' => '03123456789',
            'country' => 'Pakistan',
            'city' => 'Karachi',
            'logo' => 'logo.png',
            'calendar_type' => CalendarType::GREGORIAN,
            'locale' => Locale::EN,
            'working_style' => WorkingStyle::NORMAL,
            'business_type' => BusinessType::PHARMACY_SHOP,
            'currency_id' => Currency::where('is_base_currency', true)->first()->id,
        ]);
        $user = \App\Models\User::where('name', 'admin')->first();
        if ($user) {
            $user->company_id = $company->id;
            $user->save();
        }
         
    }
}
