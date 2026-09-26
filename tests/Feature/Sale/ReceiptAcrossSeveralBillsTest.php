<?php

namespace Tests\Feature\Sale;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Enums\TransactionStatus;
use App\Models\Receipt\Receipt;
use App\Models\Sale\Sale;
use App\Services\Accounting\SettlementService;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * One receipt settling several bills, when only one of them is wrong.
 *
 * The receipt is allocated to two invoices and only the second needs undoing.
 * There is no way to release one allocation on its own today, so the documented
 * route is to reverse the whole receipt, reverse the bad invoice, and re-enter
 * the receipt against what remains. This proves that route actually works —
 * a procedure nobody has walked is not a procedure.
 */
class ReceiptAcrossSeveralBillsTest extends TestCase
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
            'quantity' => 500,
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

    private function sell(int $number, float $total): Sale
    {
        $this->post(route('sales.store'), [
            'number' => $number,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'date' => now()->toDateString(),
            'transaction_total' => $total,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => $total / 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'));

        return Sale::query()->where('number', $number)->firstOrFail();
    }

    private function claimLineOf(Sale $sale): string
    {
        return (string) DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->where('t.reference_type', Sale::class)
            ->where('t.reference_id', $sale->id)
            ->where('t.status', 'posted')
            ->where('tl.ledger_id', $this->ctx['customer_ledger']->id)
            ->where('tl.debit', '>', 0)
            ->value('tl.id');
    }

    /** @param array<int, array{0: Sale, 1: float}> $split */
    private function receive(int $number, float $amount, array $split): Receipt
    {
        $this->post(route('receipts.store'), [
            'number' => $number,
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => $amount,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'one payment, several bills',
            'allocations' => collect($split)->map(fn (array $row) => [
                'target_line_id' => $this->claimLineOf($row[0]),
                'amount' => $row[1],
            ])->all(),
        ])->assertRedirect()->assertSessionHasNoErrors();

        return Receipt::query()->where('number', $number)->firstOrFail();
    }

    private function outstanding(): float
    {
        return (float) app(SettlementService::class)
            ->openItems($this->ctx['customer_ledger']->id)
            ->sum('remaining_amount');
    }

    public function test_the_documented_route_works_end_to_end(): void
    {
        $saleA = $this->sell(7101, 400);
        $saleB = $this->sell(7102, 600);

        $receipt = $this->receive(7150, 1000, [[$saleA, 400], [$saleB, 600]]);

        $this->assertEqualsWithDelta(0.0, $this->outstanding(), 0.01, 'Both bills start fully settled.');

        // Sale B is wrong, but the receipt holds it — as it holds Sale A.
        $this->post(route('sales.reverse', $saleB), ['reason' => 'keyed wrong'])
            ->assertSessionHasErrors('status');

        // Step 1: undo the receipt. Both allocations are released together;
        // that is the cost of having no way to release just one.
        $this->post(route('receipts.reverse', $receipt), ['reason' => 'reallocating'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertEqualsWithDelta(
            1000.0,
            $this->outstanding(),
            0.01,
            'Reversing the receipt must reopen BOTH bills, not just the one being fixed.',
        );

        // Step 2: now the bad bill is free.
        $this->post(route('sales.reverse', $saleB), ['reason' => 'keyed wrong'])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(TransactionStatus::REVERSED->value, $saleB->fresh()->status);

        $this->assertEqualsWithDelta(
            400.0,
            $this->outstanding(),
            0.01,
            'Only the good bill is left owing.',
        );

        // Step 3: the money really did arrive, so put it back against what
        // still stands.
        $this->receive(7151, 400, [[$saleA, 400]]);

        $this->assertEqualsWithDelta(
            0.0,
            $this->outstanding(),
            0.01,
            'The customer ends up square, with the cash recorded against the bill it paid.',
        );

        $this->assertSame(TransactionStatus::POSTED->value, $saleA->fresh()->status);
    }

    public function test_the_good_bill_can_be_reversed_too_once_the_receipt_is_undone(): void
    {
        $saleA = $this->sell(7201, 400);
        $saleB = $this->sell(7202, 600);
        $receipt = $this->receive(7250, 1000, [[$saleA, 400], [$saleB, 600]]);

        $this->post(route('receipts.reverse', $receipt), ['reason' => 'reallocating'])
            ->assertRedirect();

        foreach ([$saleA, $saleB] as $sale) {
            $this->post(route('sales.reverse', $sale), ['reason' => 'both wrong'])
                ->assertRedirect()
                ->assertSessionHasNoErrors();

            $this->assertSame(TransactionStatus::REVERSED->value, $sale->fresh()->status);
        }

        $this->assertEqualsWithDelta(0.0, $this->outstanding(), 0.01);
    }
}
