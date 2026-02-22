<?php

namespace Database\Seeders;

use App\Models\JournalEntry\JournalClass;
use Illuminate\Database\Seeder;

class JournalClassSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        JournalClass::factory()->count(5)->create();
    }
}
