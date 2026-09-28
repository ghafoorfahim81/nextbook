<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerType;
use App\Models\Ledger\Ledger;
use App\Services\Accounting\LedgerLinkService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Pairing the two accounts of one person.
 *
 * The pairing is symmetric, and that is the whole risk: a half-written link
 * renders perfectly from one statement and is simply absent from the other,
 * so nothing would catch it by looking. Every test here reads the link back
 * from BOTH sides.
 */
class LedgerPairingTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private LedgerLinkService $links;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
        $this->links = app(LedgerLinkService::class);
    }

    private function makeLedger(string $type, string $name, string $code): Ledger
    {
        return Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'name' => $name,
            'code' => $code,
            'type' => $type,
            'is_active' => true,
        ]);
    }

    public function test_pairing_writes_both_sides(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];

        $this->links->link($customer, $supplier);

        $this->assertSame($supplier->id, $customer->fresh()->counterpart_ledger_id);
        $this->assertSame(
            $customer->id,
            $supplier->fresh()->counterpart_ledger_id,
            'The other side of the pairing was never written.',
        );
    }

    public function test_pairing_works_the_same_from_the_supplier_side(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];

        $this->links->link($supplier, $customer);

        $this->assertSame($supplier->id, $customer->fresh()->counterpart_ledger_id);
        $this->assertSame($customer->id, $supplier->fresh()->counterpart_ledger_id);
    }

    public function test_repairing_releases_the_previous_partner(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];
        $otherSupplier = $this->makeLedger(LedgerType::SUPPLIER->value, 'Second supplier', 'SUP-2');

        $this->links->link($customer, $supplier);
        $this->links->link($customer->fresh(), $otherSupplier);

        $this->assertSame($otherSupplier->id, $customer->fresh()->counterpart_ledger_id);
        $this->assertSame($customer->id, $otherSupplier->fresh()->counterpart_ledger_id);

        // The supplier that was dropped must not still claim this customer.
        $this->assertNull(
            $supplier->fresh()->counterpart_ledger_id,
            'The replaced partner was left pointing at a customer that has moved on.',
        );
    }

    public function test_unlinking_clears_both_sides(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];

        $this->links->link($customer, $supplier);
        $this->links->unlink($customer->fresh());

        $this->assertNull($customer->fresh()->counterpart_ledger_id);
        $this->assertNull($supplier->fresh()->counterpart_ledger_id);
    }

    public function test_relinking_the_same_pair_is_harmless(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];

        $this->links->link($customer, $supplier);
        $this->links->link($customer->fresh(), $supplier->fresh());

        $this->assertSame($supplier->id, $customer->fresh()->counterpart_ledger_id);
        $this->assertSame($customer->id, $supplier->fresh()->counterpart_ledger_id);
    }

    public function test_two_accounts_of_the_same_role_cannot_be_paired(): void
    {
        $other = $this->makeLedger(LedgerType::CUSTOMER->value, 'Another customer', 'CUST-9');

        $this->expectException(ValidationException::class);

        $this->links->link($this->ctx['customer_ledger'], $other);
    }

    public function test_an_employee_account_cannot_be_paired(): void
    {
        $employee = $this->makeLedger(LedgerType::EMPLOYEE->value, 'Payroll party', 'EMP-9');

        $this->expectException(ValidationException::class);

        $this->links->link($this->ctx['customer_ledger'], $employee);
    }

    public function test_an_account_cannot_be_paired_with_itself(): void
    {
        $this->expectException(ValidationException::class);

        $this->links->link($this->ctx['customer_ledger'], $this->ctx['customer_ledger']);
    }

    // ------------------------------------------------------- through a form

    public function test_the_customer_form_pairs_and_unpairs(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];

        $this->put(route('customers.update', $customer->id), [
            'name' => $customer->name,
            'code' => $customer->code,
            'counterpart_ledger_id' => $supplier->id,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($supplier->id, $customer->fresh()->counterpart_ledger_id);
        $this->assertSame($customer->id, $supplier->fresh()->counterpart_ledger_id);

        $this->put(route('customers.update', $customer->id), [
            'name' => $customer->name,
            'code' => $customer->code,
            'counterpart_ledger_id' => '',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertNull($customer->fresh()->counterpart_ledger_id);
        $this->assertNull($supplier->fresh()->counterpart_ledger_id);
    }

    /**
     * Every query that feeds a party picker selects an explicit column list,
     * so a new column has to be added to each of them. Miss one and the
     * pairing is simply absent from that picker — no error, no warning, the
     * auto-fill just never fires.
     */
    public function test_the_picker_search_carries_the_pairing(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];

        $this->links->link($customer, $supplier);

        $response = $this->postJson('/search/ledgers', [
            'search' => $customer->name,
            'fields' => ['name'],
            'types' => ['customer'],
        ]);

        $response->assertOk();

        $row = collect($response->json('data'))->firstWhere('id', $customer->id);

        $this->assertNotNull($row, 'The customer should be findable in the picker.');
        $this->assertSame(
            $supplier->id,
            $row['counterpart_ledger_id'] ?? null,
            'The picker option dropped the pairing, so nothing downstream can use it.',
        );
    }

    public function test_a_form_that_omits_the_field_leaves_the_pairing_alone(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $supplier = $this->ctx['supplier_ledger'];

        $this->links->link($customer, $supplier);

        // Some other screen edits the party without rendering the pairing
        // field. That must not quietly break a link someone set elsewhere.
        $this->put(route('customers.update', $customer->id), [
            'name' => 'Renamed',
            'code' => $customer->code,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($supplier->id, $customer->fresh()->counterpart_ledger_id);
        $this->assertSame($customer->id, $supplier->fresh()->counterpart_ledger_id);
    }
}
