<?php

namespace Database\Seeders\Inventory;

use App\Models\Inventory\Item;
use Illuminate\Database\Seeder;
use App\Models\Administration\UnitMeasure;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use Illuminate\Support\Str;
use App\Models\Administration\Branch;
use App\Models\User;
class ItemSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $items = [
            [
                'id' => Str::ulid(),
                'name' => 'Paracetamol',
                'code' => 'ITEM1',
                'purchase_price' => 100,
                'cost' => 100,
                'mrp_rate' => 150,
                'unit_measure_id' => UnitMeasure::first()->id,
                'brand_id' => Brand::first()->id,
                'category_id' => Category::first()->id,
                'minimum_stock' => 100,
                'maximum_stock' => 100,
                'colors' => 'red',
                'size' => '100',
                'photo' => '',
                'rack_no' => 'A1',
                'fast_search' => 'paracetamol',
                'branch_id' => Branch::first()->id,
                'created_by' => User::first()->id,
            ],
            [
                'id' => Str::ulid(),
                'name' => 'florine',
                'code' => 'ITEM2',
                'purchase_price' => 100,
                'cost' => 100,
                'mrp_rate' => 150,
                'unit_measure_id' => UnitMeasure::first()->id,
                'brand_id' => Brand::first()->id,
                'category_id' => Category::first()->id,
                'minimum_stock' => 100,
                'maximum_stock' => 100,
                'colors' => 'red',
                'size' => '100',
                'photo' => '',
                'rack_no' => 'A1',
                'fast_search' => 'florine',
                'branch_id' => Branch::first()->id,
                'created_by' => User::first()->id,
            ],
            [
                'id' => Str::ulid(),
                'name' => 'ibuprofen',
                'code' => 'ITEM3',
                'purchase_price' => 100,
                'cost' => 100,
                'mrp_rate' => 150,
                'unit_measure_id' => UnitMeasure::first()->id,
                'brand_id' => Brand::first()->id,
                'category_id' => Category::first()->id,
                'minimum_stock' => 100,
                'maximum_stock' => 100,
                'colors' => 'red',
                'size' => '100',
                'photo' => '',
                'rack_no' => 'A1',
                'fast_search' => 'ibuprofen',
                'branch_id' => Branch::first()->id,
                'created_by' => User::first()->id,
            ]
        ];
        Item::insert($items);
    }
}
