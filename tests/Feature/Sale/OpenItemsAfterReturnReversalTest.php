<?php

namespace Tests\Feature\Sale;

use App\Enums\SaleReturnReason;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Sale\Sale;
use App\Models\Sale\SaleReturn;
use App\Services\Accounting\SettlementService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * What the receipt form offers to settle, after a sale return is reversed.
 *
 * The open-items list and the customer's ledger balance are two views of the
 * same debt. Whatever appears in the list, they have to add up: if the list
 * offers more than the customer owes, a receipt can be allocated against money
 * that was never owed.
 */
class OpenItemsAfterReturnReversalTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 100,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null, 'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null, 'reference_id' => null,
        ]);
    }

    private function sell(float $quantity, float $price): Sale
    {
        $this->post(route('sales.store'), [
            'number' => random_int(9000, 9899),
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => $quantity * $price,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $quantity,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => $price,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->with('items')->orderByDesc('id')->firstOrFail();
    }

    private function returnSale(Sale $sale, float $quantity): SaleReturn
    {
        $this->post(route('sale-returns.store'), [
            'number' => random_int(9900, 9999),
            'sale_id' => $sale->id,
            'date' => now()->toDateString(),
            'reason' => SaleReturnReason::values()[0],
            'description' => 'brought back',
            'item_list' => [['sale_item_id' => $sale->items->first()->id, 'quantity' => $quantity]],
        ])->assertRedirect();

        return SaleReturn::query()->orderByDesc('id')->firstOrFail();
    }

    /** What the customer actually owes, straight off the receivable account. */
    private function ledgerBalance(): float
    {
        return (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('tl.ledger_id', $this->ctx['customer_ledger']->id)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.debit - tl.credit), 0) AS b')
            ->value('b');
    }

    /** @return array{total: float, rows: \Illuminate\Support\Collection} */
    private function openItems(): array
    {
        $rows = app(SettlementService::class)->openItems($this->ctx['customer_ledger']->id);

        return [
            'total' => (float) $rows->sum('remaining_amount'),
            'rows' => $rows,
        ];
    }

    public function test_reversing_a_return_does_not_add_a_second_claim(): void
    {
        $sale = $this->sell(20, 10);            // owes 200
        $return = $this->returnSale($sale, 10); // owes 100

        $this->post(route('sale-returns.reverse', $return), ['reason' => 'raised by mistake'])
            ->assertRedirect();

        $this->assertEqualsWithDelta(
            200.0,
            $this->ledgerBalance(),
            0.01,
            'Undoing the return puts the customer back where the sale left them.',
        );

        $open = $this->openItems();

        $this->assertEqualsWithDelta(
            $this->ledgerBalance(),
            $open['total'],
            0.01,
            'The receipt form must never offer more to settle than the customer owes. Rows: '
            . $open['rows']->map(
                fn ($r) => $r['document_type'] . ' ' . $r['document_number'] . ' = ' . $r['remaining_amount']
            )->implode(' | '),
        );
    }

    /**
     * The credit a return leaves behind is not applied to the invoice it came
     * from, so while the return stands the list still offers the invoice in
     * full. That is a separate, older gap from the reversal one — recorded
     * here so a change in either direction is noticed, not asserted as
     * correct.
     */
    public function test_documents_the_unapplied_credit_note_gap(): void
    {
        $sale = $this->sell(20, 10);
        $this->returnSale($sale, 10);

        $this->assertEqualsWithDelta(100.0, $this->ledgerBalance(), 0.01);

        $this->assertEqualsWithDelta(
            200.0,
            $this->openItems()['total'],
            0.01,
            'If this now equals the balance, the credit note is being applied and this note can go.',
        );
    }

    public function test_a_reversal_voucher_is_not_offered_as_an_invoice_to_settle(): void
    {
        $sale = $this->sell(20, 10);
        $return = $this->returnSale($sale, 10);

        $this->post(route('sale-returns.reverse', $return), ['reason' => 'raised by mistake'])
            ->assertRedirect();

        $types = $this->openItems()['rows']->pluck('document_type')->unique()->values()->all();

        $this->assertNotContains(
            'reversal',
            $types,
            'A reversal voucher is not an invoice; it should never be a line the operator allocates cash to. Saw: '
            . implode(', ', $types),
        );
    }
}
