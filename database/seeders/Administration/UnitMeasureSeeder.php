<?php

namespace Database\Seeders\Administration;

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

        $branch_id = Branch::where('is_main', true)->first()->id;
        $quantities = Quantity::defaultQuantity();
        foreach ($quantities as $quantity) {
            Quantity::create([
                'id' => (string) new Ulid(),
                'quantity' => $quantity['quantity'],
                'unit' => $quantity['unit'],
                'symbol' => $quantity['symbol'],
                'branch_id' => $branch_id,
                'is_system' => $quantity['is_system'],
                'created_by' => User::where('name', 'admin')->first()->id,
            ]);
        }
        $unitMeasures = UnitMeasure::defaultUnitMeasures();
        foreach ($unitMeasures as $unitMeasure) {
                UnitMeasure::create([
                    'id' => (string) new Ulid(),
                    'name' => $unitMeasure['name'],
                    'unit' => $unitMeasure['unit'],
                    'symbol' => $unitMeasure['symbol'],
                    'quantity_id' => $unitMeasure['quantity_id'],
                    'branch_id' => $branch_id,
                    'is_system' => $unitMeasure['is_system'],
                'created_by' => User::where('name', 'admin')->first()->id,
            ]);
        }
    }
}
