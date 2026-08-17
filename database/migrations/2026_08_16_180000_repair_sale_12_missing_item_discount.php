<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * One-off data repair for sale #12.
 *
 * Sale\SaleController@store never renamed the posted `item_discount` field to the
 * `discount` column before createMany(), so line discounts were dropped on create.
 * The GL was unaffected — the discount line is built from the request's
 * `discount_total` — so sale #12 carries an 80 debit to Discount to Customer while
 * both of its sale_items rows store a 0 discount. That made the general sales
 * report read 80 higher than the GL's net revenue.
 *
 * The controller is fixed. This restores the one row written before the fix.
 *
 * The per-line split was not recoverable (the activity log stored only the header
 * total), so the full 80 is placed on the cable line at the owner's direction:
 * 30 x 36 = 1,080 less 80 gives 1,000, and 5,100 + 1,000 = 6,100, which matches
 * the cash actually received.
 *
 * Every precondition is re-checked before writing, so this is a no-op on any
 * database where the row is absent or already correct.
 */
return new class extends Migration
{
    private const SALE_NUMBER = '12';
    private const UNIT_PRICE = 36;
    private const QUANTITY = 30;
    private const DISCOUNT = 80;

    public function up(): void
    {
        $item = $this->targetItem(expectedDiscount: 0);

        if (! $item) {
            return;
        }

        DB::table('sale_items')->where('id', $item->id)->update(['discount' => self::DISCOUNT]);
    }

    public function down(): void
    {
        $item = $this->targetItem(expectedDiscount: self::DISCOUNT);

        if (! $item) {
            return;
        }

        DB::table('sale_items')->where('id', $item->id)->update(['discount' => 0]);
    }

    /**
     * Locate the cable line on sale #12, but only if the sale still looks exactly
     * as the repair assumes: no bill discount, and the GL holding the 80 that the
     * document is missing. Anything else means this is a different database or the
     * row has since been edited, and we leave it untouched.
     */
    private function targetItem(float $expectedDiscount): ?object
    {
        $sale = DB::table('sales')
            ->where('number', self::SALE_NUMBER)
            ->whereNull('deleted_at')
            ->whereNull('discount')
            ->first(['id']);

        if (! $sale) {
            return null;
        }

        $glDiscount = (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->join('accounts as a', 'a.id', '=', 'tl.account_id')
            ->where('t.reference_id', $sale->id)
            ->where('a.name', 'Discount to Customer')
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->sum('tl.debit');

        if (abs($glDiscount - self::DISCOUNT) > 0.0001) {
            return null;
        }

        return DB::table('sale_items')
            ->where('sale_id', $sale->id)
            ->where('quantity', self::QUANTITY)
            ->where('unit_price', self::UNIT_PRICE)
            ->where('discount', $expectedDiscount)
            ->whereNull('deleted_at')
            ->first(['id']);
    }
};
