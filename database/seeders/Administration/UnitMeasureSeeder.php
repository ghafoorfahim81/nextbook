<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Company;
use App\Models\Administration\Branch;
use App\Models\Administration\Quantity;
use App\Models\Administration\UnitMeasure;
use Illuminate\Database\Seeder;
use Symfony\Component\Uid\Ulid;
use App\Models\User;
class UnitMeasureSeeder extends Seeder
{
    public function run(): void
    {
//        UnitMeasure::factory()->count(5)->create();

        $companyId = Company::withoutGlobalScopes()->value('id');
        $quantities = Quantity::defaultQuantity();
        foreach ($quantities as $quantity) {
            $exists = Quantity::withoutGlobalScopes()
                ->where('slug', $quantity['slug'])
                ->exists();
            if ($exists) {
                continue;
            }
            Quantity::create([
                'id' => (string) new Ulid(),
                'quantity' => $quantity['quantity'],
                'unit' => $quantity['unit'],
                'symbol' => $quantity['symbol'],
                'slug' => $quantity['slug'],
                'company_id' => $companyId,
                'is_main' => $quantity['is_main'],
                'is_active' => false,
                'created_by' => User::first()->id,
            ]);
        }
        $unitMeasures = UnitMeasure::defaultUnitMeasures();
        foreach ($unitMeasures as $unitMeasure) {
            $exists = UnitMeasure::withoutGlobalScopes()
                ->where('name', $unitMeasure['name'])
                ->where('quantity_id', $unitMeasure['quantity_id'])
                ->exists();
            if ($exists) {
                continue;
            }
            UnitMeasure::create([
                'id' => (string) new Ulid(),
                'name' => $unitMeasure['name'],
                'unit' => $unitMeasure['unit'],
                'symbol' => $unitMeasure['symbol'],
                'quantity_id' => $unitMeasure['quantity_id'],
                'company_id' => $companyId,
                'is_main' => $unitMeasure['is_main'],
                'is_active' => false,
                'created_by' => User::where('name', 'admin')->first()->id,
            ]);
        }
    }
}
