<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerType;
use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Ledger\Ledger;
use App\Services\StockService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * A document may only name a party of the role it is for.
 *
 * The pickers filtered on search but not on the list they opened with, and the
 * requests checked only that the id existed. A purchase booked against a
 * customer was accepted, posted to Accounts Payable, and then invisible to the
 * payment form — which looks for that party's debt on Accounts Receivable,
 * because the control account is resolved from the ledger's type. The debt sat
 * in the ledger with no way to pay it.
 */
class PartyTypeIsEnforcedTest extends TestCase
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

    private function purchasePayload(Ledger $party): array
    {
        return [
            'number' => random_int(6000, 6999),
            'supplier_id' => $party->id,
            'date' => now()->toDateString(),
            'transaction_total' => 100,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'purchase_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ];
    }

    private function salePayload(Ledger $party): array
    {
        return [
            'number' => random_int(6000, 6999),
            'customer_id' => $party->id,
            'date' => now()->toDateString(),
            'transaction_total' => 100,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => 10,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 10,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ];
    }

    public function test_a_purchase_cannot_name_a_customer(): void
    {
        $this->post(route('purchases.store'), $this->purchasePayload($this->customer))
            ->assertSessionHasErrors('supplier_id');

        $this->assertDatabaseCount('purchases', 0);
    }

    public function test_a_sale_cannot_name_a_supplier(): void
    {
        $this->post(route('sales.store'), $this->salePayload($this->supplier))
            ->assertSessionHasErrors('customer_id');

        $this->assertDatabaseCount('sales', 0);
    }

    public function test_the_ordinary_case_still_works(): void
    {
        $this->post(route('purchases.store'), $this->purchasePayload($this->supplier))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->post(route('sales.store'), $this->salePayload($this->customer))
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertDatabaseCount('purchases', 1);
        $this->assertDatabaseCount('sales', 1);
    }

    public function test_an_employee_ledger_is_refused_on_both_sides(): void
    {
        $employee = Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'name' => 'Payroll party',
            'code' => 'EMP-9',
            'type' => LedgerType::EMPLOYEE->value,
            'is_active' => true,
        ]);

        $this->post(route('purchases.store'), $this->purchasePayload($employee))
            ->assertSessionHasErrors('supplier_id');

        $this->post(route('sales.store'), $this->salePayload($employee))
            ->assertSessionHasErrors('customer_id');
    }
}
