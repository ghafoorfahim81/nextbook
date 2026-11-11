<?php

namespace Tests\Feature\Purchase;

use App\Models\Account\Account;
use App\Models\Administration\UnitMeasure;
use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Prices are kept per base unit to 4 decimals; amounts are kept at cents.
 * A bag of flour bought at 3,500 is 71.4286 a kilo, and 49 kilos of it is
 * 3,500.00 in the books — never 3,500.0014.
 */
class MoneyPrecisionTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function cashLines(string $type, string $id)
    {
        return DB::table('transaction_lines')
            ->join('transactions', 'transactions.id', '=', 'transaction_lines.transaction_id')
            ->where('transactions.reference_type', $type)
            ->where('transactions.reference_id', $id);
    }

    public function test_a_kilo_price_with_four_decimals_posts_whole_cents(): void
    {
        $cash = $this->ctx['accounts']['cash-in-hand'];

        $this->post(route('purchases.store'), [
            'number' => 1,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-19',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'cash',
            'bank_account_id' => $cash->id,
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id, 'batch' => null, 'expire_date' => null,
                'quantity' => 49, 'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 71.4286, 'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('purchases.index'));

        $purchase = Purchase::with('items')->firstOrFail();
        $lines = $this->cashLines(Purchase::class, $purchase->id);

        $this->assertEquals(3500.0, (float) (clone $lines)->where('transaction_lines.account_id', $cash->id)->sum('credit'));
        $this->assertEquals(3500.0, (float) (clone $lines)->sum('debit'));
        $this->assertEquals(3500.0, $purchase->purchaseTotal());

        // The account reads at cents.
        $statement = Account::query()->find($cash->id)->statement;
        $this->assertEquals(3500.0, $statement['balance_amount']);
        $this->assertSame('3500 cr', (string) $statement['balance']);

        // The details page names the account the bill was paid from.
        $this->getJson(route('purchases.show', $purchase))
            ->assertOk()
            ->assertJsonPath('data.payment_account.id', $cash->id)
            ->assertJsonPath('data.amount', 3500);
    }

    public function test_a_sale_in_a_bigger_unit_posts_whole_cents_too(): void
    {
        $bag = UnitMeasure::factory()->create([
            'company_id' => $this->ctx['company']->id, 'quantity_id' => $this->ctx['quantity']->id,
            'name' => 'Bag', 'unit' => '49', 'symbol' => 'bag', 'is_main' => false, 'is_active' => true,
        ]);

        app(\App\Services\StockService::class)->post([
            'item_id' => $this->ctx['item']->id, 'movement_type' => 'in', 'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 98, 'source' => 'purchase', 'unit_cost' => 71.4286, 'status' => 'posted',
            'batch' => null, 'date' => '2026-03-01', 'expire_date' => null,
            'warehouse_id' => $this->ctx['warehouse']->id, 'branch_id' => $this->ctx['branch']->id,
            'reference_type' => 'seed', 'reference_id' => $this->ctx['item']->id,
        ]);

        $this->post(route('sales.store'), [
            'number' => 1,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-19',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id, 'batch' => null, 'expire_date' => null,
                'quantity' => 1, 'unit_measure_id' => $bag->id,
                'unit_price' => 4000.0001, 'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        $sale = Sale::firstOrFail();
        $lines = $this->cashLines(Sale::class, $sale->id);

        // Every amount on the voucher is whole cents.
        foreach ((clone $lines)->get(['debit', 'credit']) as $line) {
            $this->assertEquals(round((float) $line->debit, 2), (float) $line->debit);
            $this->assertEquals(round((float) $line->credit, 2), (float) $line->credit);
        }

        $this->assertEquals((float) (clone $lines)->sum('debit'), (float) (clone $lines)->sum('credit'));
        $this->assertEquals(4000.0, $sale->load('items')->saleTotal());
    }
}
