<?php

namespace Tests\Feature\Accounting;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Accounting\ContraSettlement;
use App\Models\Ledger\Ledger;
use App\Services\Accounting\SettlementService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The set-off document end to end: the form posts it, the list shows it, and
 * reversing it puts both parties back where they were.
 *
 * ContraSettlementTest covers the posting engine. This covers the module the
 * user actually touches — the route, the permissions, the document row, and
 * the one thing a half-built reversal would get wrong: leaving the clearing
 * account holding one side of an offset.
 */
class ContraSettlementModuleTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Ledger $customer;

    private Ledger $supplier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        $this->customer = $this->ctx['customer_ledger'];
        $this->supplier = $this->ctx['supplier_ledger'];

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 500,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);

        $this->sellOnLoan(5000);
        $this->buyOnLoan(3000);
    }

    private function sellOnLoan(float $total): void
    {
        $this->post(route('sales.store'), [
            'number' => random_int(1000, 1999),
            'customer_id' => $this->customer->id,
            'date' => now()->toDateString(),
            'transaction_total' => $total,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $total / 20,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'))->assertSessionHasNoErrors();
    }

    private function buyOnLoan(float $total): void
    {
        $this->post(route('purchases.store'), [
            'number' => random_int(2000, 2999),
            'supplier_id' => $this->supplier->id,
            'date' => now()->toDateString(),
            'transaction_total' => $total,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $total / 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect()->assertSessionHasNoErrors();
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'number' => 1,
            'date' => now()->toDateString(),
            'customer_ledger_id' => $this->customer->id,
            'supplier_ledger_id' => $this->supplier->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'amount' => 3000,
            'narration' => 'set-off by mutual agreement',
        ], $overrides);
    }

    private function balanceOf(Ledger $ledger): float
    {
        return (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('tl.ledger_id', $ledger->id)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->whereNull('tl.deleted_at')
            ->whereNull('t.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.debit - tl.credit), 0) AS b')
            ->value('b');
    }

    private function clearingBalance(): float
    {
        return (float) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('tl.account_id', $this->ctx['accounts']['contra-clearing']->id)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->whereNull('tl.deleted_at')
            ->selectRaw('COALESCE(SUM(tl.base_debit - tl.base_credit), 0) AS b')
            ->value('b');
    }

    public function test_the_form_posts_a_set_off_and_records_both_vouchers(): void
    {
        $this->post(route('contra-settlements.store'), $this->payload())
            ->assertRedirect(route('contra-settlements.index'))
            ->assertSessionHasNoErrors();

        $document = ContraSettlement::query()->firstOrFail();

        $this->assertSame(TransactionStatus::POSTED->value, $document->status);
        $this->assertNotNull($document->customer_transaction_id, 'The customer half was not recorded.');
        $this->assertNotNull($document->supplier_transaction_id, 'The supplier half was not recorded.');
        $this->assertNotSame(
            $document->customer_transaction_id,
            $document->supplier_transaction_id,
            'The two halves must be separate vouchers.',
        );

        $this->assertEqualsWithDelta(2000.0, $this->balanceOf($this->customer), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->balanceOf($this->supplier), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->clearingBalance(), 0.01);
    }

    public function test_offsetting_more_than_the_overlap_is_refused_and_nothing_is_saved(): void
    {
        // We only owe them 3,000.
        $this->post(route('contra-settlements.store'), $this->payload(['amount' => 4000]))
            ->assertSessionHasErrors();

        $this->assertSame(0, ContraSettlement::query()->count(), 'A refused set-off must leave no document behind.');
        $this->assertEqualsWithDelta(5000.0, $this->balanceOf($this->customer), 0.01);
        $this->assertEqualsWithDelta(-3000.0, $this->balanceOf($this->supplier), 0.01);
        $this->assertEqualsWithDelta(0.0, $this->clearingBalance(), 0.01);
    }

    public function test_the_two_sides_must_be_different_accounts(): void
    {
        $this->post(route('contra-settlements.store'), $this->payload([
            'supplier_ledger_id' => $this->customer->id,
        ]))->assertSessionHasErrors('customer_ledger_id');

        $this->assertSame(0, ContraSettlement::query()->count());
    }

    public function test_reversing_puts_both_parties_back_and_squares_clearing(): void
    {
        $this->post(route('contra-settlements.store'), $this->payload())->assertSessionHasNoErrors();

        $document = ContraSettlement::query()->firstOrFail();

        $this->post(route('contra-settlements.reverse', $document->id), ['reason' => 'agreement withdrawn'])
            ->assertRedirect();

        $document->refresh();
        $this->assertSame(TransactionStatus::REVERSED->value, $document->status);

        // Both sides owe again exactly what they owed before the set-off.
        $this->assertEqualsWithDelta(5000.0, $this->balanceOf($this->customer), 0.01);
        $this->assertEqualsWithDelta(-3000.0, $this->balanceOf($this->supplier), 0.01);
        $this->assertEqualsWithDelta(
            0.0,
            $this->clearingBalance(),
            0.01,
            'A reversal that only undid one half would strand a balance here.',
        );

        // And their documents are open again, not just their balances.
        $settlements = app(SettlementService::class);

        $this->assertEqualsWithDelta(
            5000.0,
            (float) $settlements->openItems($this->customer->id, null, SettlementService::DIRECTION_IN)->sum('remaining_amount'),
            0.01,
        );

        $this->assertEqualsWithDelta(
            3000.0,
            (float) $settlements->openItems($this->supplier->id, null, SettlementService::DIRECTION_OUT)->sum('remaining_amount'),
            0.01,
        );
    }

    public function test_a_set_off_cannot_be_reversed_twice(): void
    {
        $this->post(route('contra-settlements.store'), $this->payload())->assertSessionHasNoErrors();

        $document = ContraSettlement::query()->firstOrFail();

        $this->post(route('contra-settlements.reverse', $document->id), [])->assertRedirect();
        $this->post(route('contra-settlements.reverse', $document->id), [])->assertStatus(422);
    }

    /**
     * A set-off moves a party's balance without a sale, a purchase or any
     * cash. If it appears on none of their tabs, the balance simply changed
     * and nothing on the page says why — which is how the gap was found.
     */
    public function test_both_parties_can_see_the_set_off_on_their_own_page(): void
    {
        $this->post(route('contra-settlements.store'), $this->payload())->assertSessionHasNoErrors();

        $customerPage = $this->getJson(route('customers.show', $this->customer->id));
        $customerPage->assertOk();

        $this->assertCount(1, $customerPage->json('contraSettlements'));
        $this->assertEqualsWithDelta(3000.0, $customerPage->json('contraSettlements.0.amount'), 0.01);
        $this->assertSame(
            $this->supplier->name,
            $customerPage->json('contraSettlements.0.counterparty'),
            'Read from the customer page, the row should name the supplier account it cancelled against.',
        );

        $supplierPage = $this->getJson(route('suppliers.show', $this->supplier->id));
        $supplierPage->assertOk();

        $this->assertCount(1, $supplierPage->json('contraSettlements'));
        $this->assertSame(
            $this->customer->name,
            $supplierPage->json('contraSettlements.0.counterparty'),
            'And from the supplier page, the customer account.',
        );
    }

    public function test_the_list_and_the_detail_page_render(): void
    {
        $this->post(route('contra-settlements.store'), $this->payload())->assertSessionHasNoErrors();

        $document = ContraSettlement::query()->firstOrFail();

        $this->get(route('contra-settlements.index'))->assertOk();
        $this->get(route('contra-settlements.create'))->assertOk();
        $this->get(route('contra-settlements.show', $document->id))->assertOk();
    }
}
