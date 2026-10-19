<?php

use App\Enums\CostingMethod;
use App\Enums\PricingMethod;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fields that let one items table serve every business type.
 *
 * A business type is a *combination of flags*, not a new schema: a pharmacy is
 * batch + expiry + prescription, a mobile shop is serial + warranty, a
 * supermarket is expiry + weighted. Nothing here is business-specific on its
 * own.
 *
 * is_batch_tracked and is_expiry_tracked are deliberately left as they are.
 * They are independent, not collapsible into one enum: most supermarket
 * perishables are expiry-tracked with no batch code at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // --- Behaviour flags -------------------------------------------
            $table->boolean('is_active')->default(true)->index();
            $table->boolean('is_stockable')->default(true);   // false for services
            $table->boolean('is_sellable')->default(true);    // raw materials: false
            $table->boolean('is_purchasable')->default(true);
            $table->boolean('is_serial_tracked')->default(false);
            $table->boolean('is_weighted')->default(false);   // produce sold by kg
            $table->boolean('has_variants')->default(false);
            $table->boolean('allow_negative_stock')->default(false);

            // --- Costing / pricing -----------------------------------------
            // Null falls back to the company default. A jewellery shop sells
            // gold (specific) and gift boxes (weighted average) at once, which
            // one company-wide setting cannot express.
            $table->enum('costing_method', CostingMethod::values())->nullable();
            // Reserved: inert until the market-rate module lands.
            $table->enum('pricing_method', PricingMethod::values())
                ->default(PricingMethod::FIXED->value);

            // --- Physical ---------------------------------------------------
            $table->text('description')->nullable();
            $table->string('photo')->nullable();   // column was missing entirely
            $table->decimal('weight', 18, 4)->nullable();
            $table->decimal('length', 18, 4)->nullable();
            $table->decimal('width', 18, 4)->nullable();
            $table->decimal('height', 18, 4)->nullable();

            // --- Sourcing / compliance --------------------------------------
            $table->string('manufacturer')->nullable();
            $table->string('country_of_origin')->nullable();
            $table->string('hs_code')->nullable();
            $table->integer('warranty_months')->nullable();
            // Days from manufacture to expiry: lets goods-receipt fill the
            // expiry date instead of the storekeeper typing it every time.
            $table->integer('shelf_life_days')->nullable();
            // Refuse a delivery arriving with less than this share of its life
            // left. Retail practice is to reject below 75%.
            $table->integer('min_shelf_life_percent')->nullable();
            $table->string('storage_zone')->nullable();   // ambient|chilled|frozen
            $table->boolean('requires_prescription')->default(false);
            $table->boolean('is_controlled')->default(false);

            // --- Planning ----------------------------------------------------
            $table->decimal('reorder_quantity', 18, 4)->nullable();
            $table->integer('lead_time_days')->nullable();
            $table->ulid('default_warehouse_id')->nullable()->index();

            // Descriptive (non variant-forming) attributes.
            $table->jsonb('attributes')->nullable();
        });

        Schema::table('items', function (Blueprint $table) {
            $table->foreign('default_warehouse_id')->references('id')->on('warehouses');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['default_warehouse_id']);
            $table->dropColumn([
                'is_active', 'is_stockable', 'is_sellable', 'is_purchasable',
                'is_serial_tracked', 'is_weighted', 'has_variants', 'allow_negative_stock',
                'costing_method', 'pricing_method',
                'description', 'photo', 'weight', 'length', 'width', 'height',
                'manufacturer', 'country_of_origin', 'hs_code', 'warranty_months',
                'shelf_life_days', 'min_shelf_life_percent', 'storage_zone',
                'requires_prescription', 'is_controlled',
                'reorder_quantity', 'lead_time_days', 'default_warehouse_id',
                'attributes',
            ]);
        });
    }
};
