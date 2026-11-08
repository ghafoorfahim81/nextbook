<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * landed_cost_purchases is the record of which bills a landed cost covers; the
 * purchase_id column only repeated its first row. Any link held by the column
 * alone is copied to the pivot before the column goes.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('landed_costs')
            ->whereNotNull('purchase_id')
            ->whereNotExists(function ($query): void {
                $query->select(DB::raw(1))
                    ->from('landed_cost_purchases')
                    ->whereColumn('landed_cost_purchases.landed_cost_id', 'landed_costs.id')
                    ->whereColumn('landed_cost_purchases.purchase_id', 'landed_costs.purchase_id');
            })
            ->select(['id', 'company_id', 'purchase_id'])
            ->get()
            ->each(function ($landedCost): void {
                DB::table('landed_cost_purchases')->insert([
                    'id' => (string) Str::ulid(),
                    'company_id' => $landedCost->company_id,
                    'landed_cost_id' => $landedCost->id,
                    'purchase_id' => $landedCost->purchase_id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });

        Schema::table('landed_costs', function (Blueprint $table): void {
            $table->dropColumn('purchase_id');
        });
    }

    public function down(): void
    {
        Schema::table('landed_costs', function (Blueprint $table): void {
            $table->ulid('purchase_id')->nullable()->index();
        });

        DB::statement("
            UPDATE landed_costs SET purchase_id = first_link.purchase_id
            FROM (
                SELECT DISTINCT ON (landed_cost_id) landed_cost_id, purchase_id
                FROM landed_cost_purchases
                ORDER BY landed_cost_id, created_at, id
            ) first_link
            WHERE landed_costs.id = first_link.landed_cost_id
        ");
    }
};
