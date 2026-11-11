<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerType;
use App\Models\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * One person can be both a customer and a supplier. That is two ledgers with the
 * same name, which must be accepted; two customers of one name still must not,
 * and that has to come back as a validation message rather than a database error.
 */
class SamePartyNameTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    private function make(string $name, LedgerType $type, string $code, array $extra = []): Ledger
    {
        return Ledger::factory()->create(array_merge([
            'company_id' => $this->ctx['company']->id,
            'currency_id' => $this->ctx['currency']->id,
            'name' => $name,
            'code' => $code,
            'type' => $type->value,
        ], $extra));
    }

    private function payload(string $name, string $code): array
    {
        return [
            'name' => $name,
            'code' => $code,
            'currency_id' => $this->ctx['currency']->id,
        ];
    }

    public function test_a_supplier_may_share_the_name_of_a_customer(): void
    {
        $this->make('Farhad', LedgerType::CUSTOMER, 'CUST-900', ['phone_no' => '0700000001', 'email' => 'farhad@example.test']);

        $this->post(route('suppliers.store'), $this->payload('Farhad', 'SUP-900'))
            ->assertSessionHasNoErrors();

        $this->assertSame(2, Ledger::query()->where('name', 'Farhad')->count());
    }

    public function test_the_same_phone_and_email_may_appear_on_both_sides(): void
    {
        $this->make('Farhad', LedgerType::CUSTOMER, 'CUST-901', ['phone_no' => '0700000002', 'email' => 'f2@example.test']);

        $this->make('Farhad', LedgerType::SUPPLIER, 'SUP-901', ['phone_no' => '0700000002', 'email' => 'f2@example.test']);

        $this->assertSame(2, Ledger::query()->where('phone_no', '0700000002')->count());
    }

    public function test_two_customers_cannot_share_a_name_and_it_is_a_validation_error(): void
    {
        $this->make('Farhad', LedgerType::CUSTOMER, 'CUST-902');

        $this->post(route('customers.store'), $this->payload('Farhad', 'CUST-903'))
            ->assertSessionHasErrors('name');

        $this->assertSame(1, Ledger::query()->where('name', 'Farhad')->count());
    }

    public function test_renaming_a_supplier_to_a_customers_name_is_allowed_but_not_to_another_suppliers(): void
    {
        $this->make('Farhad', LedgerType::CUSTOMER, 'CUST-904');
        $other = $this->make('Karim', LedgerType::SUPPLIER, 'SUP-904');
        $mine = $this->make('Nasir', LedgerType::SUPPLIER, 'SUP-905');

        $this->put(route('suppliers.update', $mine), $this->payload('Farhad', 'SUP-905'))
            ->assertSessionHasNoErrors();
        $this->assertSame('Farhad', $mine->fresh()->name);

        $this->put(route('suppliers.update', $mine), $this->payload('Karim', 'SUP-905'))
            ->assertSessionHasErrors('name');
        $this->assertSame('Farhad', $mine->fresh()->name);

        // Saving a supplier under its own name is not a clash with itself.
        $this->put(route('suppliers.update', $other), $this->payload('Karim', 'SUP-904'))
            ->assertSessionHasNoErrors();
    }
}
