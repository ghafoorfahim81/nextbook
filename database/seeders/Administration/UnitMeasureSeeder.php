<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Branch;
use App\Models\Administration\Quantity;
use App\Models\Administration\UnitMeasure;
use Illuminate\Database\Seeder;

class UnitMeasureSeeder extends Seeder
{
    public function run(): void
    {
//        UnitMeasure::factory()->count(5)->create();

        $branch_id = Branch::first()->id;
        $pcs = Quantity::create([
            'quantity' => 'Count',
            'unit'       => "Pcs",
            'symbol'     => "ea",
            'branch_id'  => $branch_id,
        ]);

        UnitMeasure::create([
            'name'        => 'pcs',
            'unit'        => 1,
            'symbol'      => "ea",
            'quantity_id' => $pcs->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'Pair',
            'unit'        => 2,
            'symbol'      => "pr",
            'quantity_id' => $pcs->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'Dozen',
            'unit'        => 12,
            'symbol'      => "dz",
            'quantity_id' => $pcs->id,
            'branch_id'   => $branch_id,
        ]);

        $length = Quantity::create([
            'quantity' => 'Length',
            'unit'       => "Centimetre",
            'symbol'     => "cm",
            'branch_id'  => $branch_id,
        ]);

        UnitMeasure::create([
            'name'        => 'Centimetre',
            'unit'        => 1,
            'symbol'      => "cm",
            'quantity_id' => $length->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'Inch',
            'unit'        => 2.5,
            'symbol'      => "in",
            'quantity_id' => $length->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'Meter',
            'unit'        => 100,
            'symbol'      => "m",
            'quantity_id' => $length->id,
            'branch_id'   => $branch_id,
        ]);

        $area = Quantity::create([
            'quantity' => 'Area',
            'unit'       => "SquareCentimetre",
            'symbol'     => "cm2",
            'branch_id'  => $branch_id,
        ]);

        UnitMeasure::create([
            'name'        => 'SquareCentimetre',
            'unit'        => 1,
            'symbol'      => "cm2",
            'quantity_id' => $area->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'SquareDecimeter',
            'unit'        => 0.01,
            'symbol'      => "dm2",
            'quantity_id' => $area->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'SquareMeter',
            'unit'        => 0.0001,
            'symbol'      => "m2",
            'quantity_id' => $area->id,
            'branch_id'   => $branch_id,
        ]);

        $weight = Quantity::create([
            'quantity' => 'Weight',
            'unit'       => "Gram",
            'symbol'     => "g",
            'branch_id'  => $branch_id,
        ]);

        UnitMeasure::create([
            'name'        => 'Gram',
            'unit'        => 1,
            'symbol'      => "g",
            'quantity_id' => $weight->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'Kilogram',
            'unit'        => 1000,
            'symbol'      => "kg",
            'quantity_id' => $weight->id,
            'branch_id'   => $branch_id,
        ]);
        UnitMeasure::create([
            'name'        => 'Ton',
            'unit'        => 1000000,
            'symbol'      => "ton",
            'quantity_id' => $weight->id,
            'branch_id'   => $branch_id,
        ]);
    }
}
