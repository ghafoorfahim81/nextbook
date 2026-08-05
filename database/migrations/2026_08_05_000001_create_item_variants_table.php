<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A variant is one sellable product: the thing a customer actually chooses.
 *
 * SKU, barcode, price and reorder level live here rather than on the item,
 * because a 512GB phone and a 128GB phone are priced, scanned and stocked
 * separately even though they share a catalogue entry.
 *
 * Variant-forming values are held in `attributes` (JSONB) rather than as
 * columns, so a new business type adds rows, never a migration. `variant_key`
 * is a canonical, sorted fingerprint of those attributes and is what actually
 * enforces uniqueness — two JSONB objects with the same pairs in a different
 * order are equal in meaning but not as text.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('item_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('item_id')->index();

            $table->jsonb('attributes')->default('{}');
            $table->string('variant_key');
            $table->string('name')->nullable();

            $table->string('sku')->nullable();
            $table->string('barcode')->nullable();

            $table->decimal('sale_price', 18, 4)->nullable();
            $table->decimal('purchase_price', 18, 4)->nullable();
            // Derived from postings, never entered by hand. Falls back to
            // items.avg_cost when a business prices all variants alike.
            $table->decimal('avg_cost', 18, 4)->default(0);
            $table->decimal('minimum_stock', 18, 4)->nullable();
            $table->decimal('maximum_stock', 18, 4)->nullable();

            $table->decimal('weight', 18, 4)->nullable();
            // Sizes sort S, M, L, XL — not alphabetically.
            $table->integer('sort_order')->default(0);
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'item_id']);
        });

        Schema::table('item_variants', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items')->cascadeOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });

        // Partial indexes, not `unique(..., deleted_at)`. Including deleted_at
        // in a plain UNIQUE never fires on live rows, because NULL != NULL in
        // Postgres. And NULLS NOT DISTINCT would be wrong here in the other
        // direction: it would make every barcode-less variant collide with
        // every other one. Excluding the NULLs is what we actually want.
        DB::statement('CREATE UNIQUE INDEX item_variants_identity_unique
            ON item_variants (item_id, variant_key) WHERE deleted_at IS NULL');
        DB::statement('CREATE UNIQUE INDEX item_variants_barcode_unique
            ON item_variants (branch_id, barcode)
            WHERE deleted_at IS NULL AND barcode IS NOT NULL');
        DB::statement('CREATE UNIQUE INDEX item_variants_sku_unique
            ON item_variants (branch_id, sku)
            WHERE deleted_at IS NULL AND sku IS NOT NULL');

        DB::statement('CREATE INDEX item_variants_attributes_gin
            ON item_variants USING GIN (attributes)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_variants');
    }
};
