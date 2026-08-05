<?php

use App\Enums\LotStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A lot is one delivery of stock sharing the same characteristics.
 *
 * Both identifiers are nullable and an expiry-only lot is the common case, not
 * an edge case: milk and bread carry a printed best-before date and no batch
 * code at all. Paint carries a batch and no expiry. Salt carries neither, and
 * gets no lot row.
 *
 * Price lives here as well as on the variant because they are different
 * prices: the variant holds the catalogue price, the lot holds what *this*
 * delivery sells for — the MRP printed on that production run, a near-expiry
 * markdown, or old stock bought before a price rise. Resolution is
 * lot -> variant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_lots', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->ulid('item_id')->index();
            // Null means the lot applies to every variant of the item, which is
            // what a supermarket with simple items will use.
            $table->ulid('variant_id')->nullable()->index();

            $table->string('batch_no')->nullable();
            $table->date('expire_date')->nullable()->index();
            $table->date('manufacture_date')->nullable();

            $table->decimal('mrp', 18, 4)->nullable();
            $table->decimal('sale_price', 18, 4)->nullable();
            $table->decimal('unit_cost', 18, 4)->nullable();
            $table->decimal('purchase_price', 18, 4)->nullable();

            $table->date('received_date')->nullable();
            $table->enum('status', LotStatus::values())->default(LotStatus::ACTIVE->value);

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['branch_id', 'item_id', 'expire_date']);
        });

        Schema::table('stock_lots', function (Blueprint $table) {
            $table->foreign('item_id')->references('id')->on('items');
            $table->foreign('variant_id')->references('id')->on('item_variants');
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users');
        });

        // NULLS NOT DISTINCT is genuinely required here, unlike on variants:
        // two lots both with batch_no NULL and the same expiry ARE the same
        // lot, so the NULLs must compare equal. Without it you would silently
        // get duplicate milk lots, each carrying its own price.
        // Partial on deleted_at so soft-deleted lots don't block re-creation.
        DB::statement('CREATE UNIQUE INDEX stock_lots_identity_unique
            ON stock_lots (branch_id, item_id, variant_id, batch_no, expire_date)
            NULLS NOT DISTINCT WHERE deleted_at IS NULL');
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_lots');
    }
};
