<?php

namespace Tests\Feature\Ledger;

use App\Enums\LedgerType;
use App\Models\Administration\Branch;
use App\Models\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Phase 2, step 9: one customer record per company, offered to the branches
 * it is open to.
 *
 * The ledger and its balance belong to the company. ledger_branches only
 * decides which branches' document pickers show a customer or supplier.
 */
class LedgerBranchesTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Branch $herat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        $this->herat = Branch::factory()->create([
            'company_id' => $this->ctx['company']->id,
            'name' => 'Herat',
            'is_main' => false,
        ]);
    }

    private function customer(string $name): Ledger
    {
        return Ledger::factory()->create([
            'company_id' => $this->ctx['company']->id,
            'name' => $name,
            'type' => LedgerType::CUSTOMER->value,
            'currency_id' => $this->ctx['currency']->id,
            'is_active' => true,
        ]);
    }

    /** @return array<int, string> */
    private function pickerIds(string $name, array $types = ['customer']): array
    {
        return collect($this->postJson('/search/ledgers', [
            'search' => $name,
            'fields' => ['name'],
            'types' => $types,
        ])->assertOk()->json('data'))->pluck('id')->all();
    }

    /**
     * Switch the acting branch the way the app does: SetActiveBranch reads it
     * from the session on every request, so binding the container alone would
     * not survive into the request.
     */
    private function actIn(Branch $branch): void
    {
        session(['active_branch_id' => $branch->id]);
        app()->instance('active_branch_id', $branch->id);
    }

    public function test_a_new_customer_starts_in_the_branch_that_created_it(): void
    {
        $customer = $this->customer('Kabul Only Trader');

        $this->assertSame(
            [$this->ctx['branch']->id],
            $customer->branches()->pluck('branches.id')->all()
        );
    }

    public function test_another_branch_of_the_company_does_not_see_it_until_opened(): void
    {
        $customer = $this->customer('Kabul Only Trader');

        $this->actIn($this->herat);
        $this->assertNotContains($customer->id, $this->pickerIds('Kabul Only Trader'));

        $customer->openToBranches([$this->ctx['branch']->id, $this->herat->id]);

        $this->assertContains($customer->id, $this->pickerIds('Kabul Only Trader'));
    }

    public function test_the_creating_branch_sees_it(): void
    {
        $customer = $this->customer('Kabul Only Trader');

        $this->assertContains($customer->id, $this->pickerIds('Kabul Only Trader'));
    }

    /**
     * Employee ledgers follow the employee, not a branch list: they have no
     * rows in ledger_branches and stay visible as before.
     */
    public function test_employee_ledgers_are_not_filtered(): void
    {
        $employee = Ledger::factory()->create([
            'company_id' => $this->ctx['company']->id,
            'name' => 'Payroll Person',
            'type' => LedgerType::EMPLOYEE->value,
            'currency_id' => $this->ctx['currency']->id,
            'is_active' => true,
        ]);

        $this->assertSame(0, $employee->branches()->count());

        $this->actIn($this->herat);
        $this->assertContains($employee->id, $this->pickerIds('Payroll Person', ['employee']));
    }

    public function test_the_form_opens_a_customer_to_more_branches(): void
    {
        $customer = $this->customer('Two Branch Trader');

        $this->put(route('customers.update', $customer), [
            'name' => 'Two Branch Trader',
            'branch_ids' => [$this->ctx['branch']->id, $this->herat->id],
        ])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing(
            [$this->ctx['branch']->id, $this->herat->id],
            $customer->branches()->pluck('branches.id')->all()
        );
    }

    public function test_a_customer_cannot_be_opened_to_another_companys_branch(): void
    {
        $customer = $this->customer('Guarded Trader');
        $foreign = $this->branchInAnotherCompany();

        $this->put(route('customers.update', $customer), [
            'name' => 'Guarded Trader',
            'branch_ids' => [$foreign->id],
        ])->assertSessionHasErrors('branch_ids.0');

        $this->assertSame(
            [$this->ctx['branch']->id],
            $customer->branches()->pluck('branches.id')->all()
        );
    }

    public function test_an_empty_branch_list_is_refused(): void
    {
        $customer = $this->customer('Nowhere Trader');

        $this->put(route('customers.update', $customer), [
            'name' => 'Nowhere Trader',
            'branch_ids' => [],
        ])->assertSessionHasErrors('branch_ids');
    }
}
