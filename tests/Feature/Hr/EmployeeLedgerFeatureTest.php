<?php

namespace Tests\Feature\Hr;

use App\Enums\LedgerType;
use App\Models\Hr\Employee;
use App\Models\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The employee/ledger split is the load-bearing design decision of the module:
 * HR data lives on `employees`, the money lives on a companion EMPLOYEE-type
 * `ledgers` row, and EmployeeObserver keeps the two in step.
 *
 * Most of what can go wrong here is silent — a missing ledger only shows up when
 * payroll tries to pay someone, and a ledger stranded in the wrong branch only
 * shows up when a settlement finds no open items.
 */
class EmployeeLedgerFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function makeEmployee(array $attributes = []): Employee
    {
        return Employee::factory()->create(array_merge([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
        ], $attributes));
    }

    public function test_creating_an_employee_creates_exactly_one_employee_ledger(): void
    {
        $employee = $this->makeEmployee(['code' => 'EMP-0001', 'first_name' => 'Ahmad', 'last_name' => 'Karimi']);

        $this->assertNotNull($employee->ledger_id);

        $ledger = Ledger::withoutGlobalScopes()->find($employee->ledger_id);

        $this->assertNotNull($ledger);
        $this->assertSame(LedgerType::EMPLOYEE->value, $ledger->type->value);
        $this->assertSame('EMP-0001', $ledger->code);
        $this->assertSame('Ahmad Karimi (EMP-0001)', $ledger->name);

        $this->assertSame(
            1,
            Ledger::withoutGlobalScopes()->where('type', LedgerType::EMPLOYEE->value)->count()
        );
    }

    /**
     * The ledger must land in the EMPLOYEE's branch, not whichever branch the
     * acting user happens to be switched to — otherwise the employee cannot be
     * settled from their own branch.
     */
    public function test_the_ledger_takes_the_employees_branch_not_the_acting_branch(): void
    {
        $otherBranch = \App\Models\Administration\Branch::factory()->create(['is_main' => false]);

        $employee = Employee::factory()->create([
            'branch_id' => $otherBranch->id,
            'currency_id' => $this->ctx['currency']->id,
        ]);

        $ledger = Ledger::withoutGlobalScopes()->find($employee->ledger_id);

        $this->assertSame($otherBranch->id, $ledger->branch_id);
        $this->assertNotSame($this->ctx['branch']->id, $ledger->branch_id);
    }

    /**
     * `ledgers` is unique on (branch, name). Two people really can share a name,
     * so the code suffix is what keeps the second one saveable.
     */
    public function test_two_employees_with_the_same_name_both_get_ledgers(): void
    {
        $a = $this->makeEmployee(['code' => 'EMP-0001', 'first_name' => 'Ahmad', 'last_name' => 'Karimi']);
        $b = $this->makeEmployee(['code' => 'EMP-0002', 'first_name' => 'Ahmad', 'last_name' => 'Karimi']);

        $this->assertNotNull($a->ledger_id);
        $this->assertNotNull($b->ledger_id);
        $this->assertNotSame($a->ledger_id, $b->ledger_id);
    }

    /**
     * `ledgers` is also unique on (branch, phone_no) and (branch, email).
     * Copying an employee's contacts across would make an employee who shares a
     * phone with a customer impossible to save.
     */
    public function test_an_employee_sharing_a_customer_phone_and_email_still_saves(): void
    {
        $customer = $this->ctx['customer_ledger'];
        $customer->forceFill(['phone_no' => '0700000000', 'email' => 'office@example.com'])->save();

        $employee = $this->makeEmployee([
            'phone_number' => '0700000000',
            'email' => 'office@example.com',
        ]);

        $ledger = Ledger::withoutGlobalScopes()->find($employee->ledger_id);

        $this->assertNotNull($ledger);
        $this->assertNull($ledger->phone_no);
        $this->assertNull($ledger->email);
        // The details are still on the employee, where the HR screens read them.
        $this->assertSame('0700000000', $employee->phone_number);
    }

    public function test_renaming_an_employee_renames_the_ledger(): void
    {
        $employee = $this->makeEmployee(['code' => 'EMP-0007', 'first_name' => 'Ahmad', 'last_name' => 'Karimi']);

        $employee->update(['first_name' => 'Ahmad Shah']);

        $ledger = Ledger::withoutGlobalScopes()->find($employee->ledger_id);

        $this->assertSame('Ahmad Shah Karimi (EMP-0007)', $ledger->name);
    }

    public function test_editing_an_unmirrored_field_does_not_touch_the_ledger(): void
    {
        $employee = $this->makeEmployee();
        $ledger = Ledger::withoutGlobalScopes()->find($employee->ledger_id);
        $before = $ledger->updated_at;

        $employee->update(['present_address' => 'Somewhere else entirely']);

        $this->assertEquals(
            $before,
            Ledger::withoutGlobalScopes()->find($employee->ledger_id)->updated_at
        );
    }

    public function test_deleting_an_employee_cascades_to_the_ledger(): void
    {
        $employee = $this->makeEmployee();
        $ledgerId = $employee->ledger_id;

        $employee->delete();

        $this->assertSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertSoftDeleted('ledgers', ['id' => $ledgerId]);
    }

    /**
     * This is what makes the Undo toast work end to end.
     */
    public function test_restoring_an_employee_restores_the_ledger(): void
    {
        $employee = $this->makeEmployee();
        $ledgerId = $employee->ledger_id;

        $employee->delete();
        $employee->restore();

        $this->assertNotSoftDeleted('employees', ['id' => $employee->id]);
        $this->assertNotSoftDeleted('ledgers', ['id' => $ledgerId]);
    }

    /**
     * A ledger deleted deliberately and earlier is not part of this employee's
     * cascade, so restoring the employee must leave it deleted. Same threshold
     * rule HasAttachments uses.
     */
    public function test_restore_does_not_revive_a_separately_deleted_ledger(): void
    {
        $employee = $this->makeEmployee();
        $ledgerId = $employee->ledger_id;

        Ledger::withoutGlobalScopes()->find($ledgerId)->delete();
        $this->travel(2)->days();
        $employee->delete();

        $employee->restore();

        $this->assertSoftDeleted('ledgers', ['id' => $ledgerId]);
    }

    public function test_employee_ledger_reports_a_credit_normal_balance(): void
    {
        $employee = $this->makeEmployee();

        $statement = Ledger::withoutGlobalScopes()->find($employee->ledger_id)->statement;

        $this->assertSame('cr', $statement['normal_balance_nature']);
    }

    public function test_next_code_skips_codes_taken_by_trashed_employees(): void
    {
        $employee = $this->makeEmployee(['code' => 'EMP-0001']);
        $employee->delete();

        // Reusing EMP-0001 would collide the moment the trashed row is restored.
        $this->assertSame('EMP-0002', Employee::nextCode());
    }
}
