<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Convert values persisted before BusinessType was simplified.
     */
    public function up(): void
    {
        $businessTypes = [
            'pharmacy',
            'supermarket',
            'manufacturing',
            'retail',
            'accounting',
            'automobile',
            'book',
            'clothing',
            'hotel',
            'carpet',
            'electronics',
            'computer',
            'jewellery',
            'mobile',
            'pharma_manufacturing',
            'ply_carpet',
            'restaurant',
        ];

        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_business_type_check');
        DB::statement("ALTER TABLE companies ALTER COLUMN business_type SET DEFAULT 'pharmacy'");

        foreach ([
            'pharmacy_shop' => 'pharmacy',
            'pharma_distribution' => 'pharmacy',
            'supermarket_grocery' => 'supermarket',
            'billing_general' => 'retail',
            'book_author_publisher' => 'book',
            'garment_size_wise' => 'clothing',
            'hotel_resorts' => 'hotel',
            'mobile_serial_wise' => 'mobile',
            'pharma_manufacturing_batch' => 'pharma_manufacturing',
            'ply_carpet_feet_mtr_wise' => 'ply_carpet',
            'restaurant_table_wise' => 'restaurant',
        ] as $legacyValue => $value) {
            DB::table('companies')
                ->where('business_type', $legacyValue)
                ->update(['business_type' => $value]);
        }

        $allowedValues = implode(', ', array_map(
            fn (string $businessType): string => "'{$businessType}'",
            $businessTypes,
        ));

        DB::statement(
            "ALTER TABLE companies ADD CONSTRAINT companies_business_type_check
            CHECK (business_type IS NULL OR business_type IN ({$allowedValues}))",
        );
    }

    public function down(): void
    {
        // This data normalization cannot be safely reversed: multiple legacy
        // business types are intentionally consolidated into one current type.
    }
};
