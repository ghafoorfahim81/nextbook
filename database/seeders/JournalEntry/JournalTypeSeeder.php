<?php

namespace Database\Seeders;

use App\Models\JournalType;
use Illuminate\Database\Seeder;

class JournalTypeSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JournalType::factory()->count(5)->create();
    }
}
