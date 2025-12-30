<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Currency;
use Illuminate\Database\Seeder;
use App\Models\Administration\Branch;

class CurrencySeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $branch = Branch::where('is_main', true)->first();
        $currencyList = Currency::defaultCurrencies();
        
        foreach ($currencyList as $key => $value) {
            $temp = $value + ['code' => $key];
            // Ensure is_base_currency is set for all currencies
            if (!isset($temp['is_base_currency'])) {
                $temp['is_base_currency'] = false;
            }
            Currency::create($temp);
        }
    }
}
