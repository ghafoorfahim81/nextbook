<?php

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Automatic sale discounts, scoped to an item, a category, a brand, or
 * everything.
 *
 * A rule table rather than a `discount` column on items/categories: a column
 * can only ever say "this item, always", and cannot express a promo that ends
 * on a date, a deal that only applies to one customer, or a bulk break at a
 * minimum quantity. Purchase is deliberately out — a supplier's discount is
 * negotiated per bill, not derivable from the item.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('discount_rules', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');

            $table->enum('scope', DiscountScope::values())->default(DiscountScope::ITEM->value);
            // Null for the ALL scope; otherwise the item / category / brand id.
            // Left unconstrained on purpose: one column pointing at three
            // different tables cannot carry a foreign key.
            $table->ulid('scope_id')->nullable()->index();

            $table->enum('discount_type', DiscountType::values())->default(DiscountType::PERCENTAGE->value);
            $table->decimal('value', 18, 4);

            // Optional narrowing. A rule tied to a customer group beats the same
            // rule without one; a rule with a minimum only fires at that
            // quantity. Group rather than a single customer: "wholesale" and
            // "VIP" are the deals shops actually strike, and a one-off buyer
            // can be put in a group of one.
            $table->ulid('customer_group_id')->nullable()->index();
            $table->decimal('min_quantity', 18, 4)->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();

            // Tie-breaker when two rules are equally specific.
            $table->integer('priority')->default(0);
            $table->boolean('is_active')->default(true);
            // Their call per rule: some shops want the discount itemised on the
            // invoice, others want it folded quietly into the price.
            $table->boolean('show_on_invoice')->default(true);

            $table->ulid('branch_id')->index();
            $table->ulid('created_by')->index();
            $table->ulid('updated_by')->nullable();
            $table->ulid('deleted_by')->nullable();

            $table->index(['branch_id', 'is_active', 'scope', 'scope_id']);

            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('discount_rules', function (Blueprint $table) {
            $table->foreign('customer_group_id')->references('id')->on('customer_groups')->nullOnDelete();
            $table->foreign('branch_id')->references('id')->on('branches');
            $table->foreign('created_by')->references('id')->on('users');
            $table->foreign('updated_by')->references('id')->on('users');
            $table->foreign('deleted_by')->references('id')->on('users')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('discount_rules');
    }
};
