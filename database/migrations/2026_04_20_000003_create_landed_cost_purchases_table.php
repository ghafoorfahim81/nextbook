<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        Schema::create('landed_cost_purchases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('landed_cost_id')->index();
            $table->ulid('purchase_id')->index();
            $table->timestamps();

            $table->unique(['landed_cost_id', 'purchase_id']);
            $table->foreign('landed_cost_id')->references('id')->on('landed_costs')->cascadeOnDelete();
            $table->foreign('purchase_id')->references('id')->on('purchases')->cascadeOnDelete();
        });

        DB::table('landed_costs')
            ->whereNotNull('purchase_id')
            ->select(['id', 'purchase_id'])
            ->get()
            ->each(function ($landedCost): void {
                DB::table('landed_cost_purchases')->insert([
                    'id' => (string) Str::ulid(),
                    'landed_cost_id' => $landedCost->id,
                    'purchase_id' => $landedCost->purchase_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::dropIfExists('landed_cost_purchases');
    }
};
