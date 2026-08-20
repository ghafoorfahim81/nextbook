<?php

namespace Tests\Feature\Hr;

use App\Enums\LedgerType;
use App\Models\Administration\Branch;
use App\Models\Hr\Employee;
use App\Models\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class EmployeeCrudFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'code' => 'EMP-0001',
            'first_name' => 'Ahmad',
            'last_name' => 'Karimi',
            'father_name' => 'Nasir',
            'employment_type' => 'permanent',
            'employment_status' => 'active',
            'joining_date' => '2026-01-15',
            'currency_id' => $this->ctx['currency']->id,
            'basic_salary' => 25000,
            'is_active' => true,
        ], $overrides);
    }

    public function test_it_creates_an_employee_and_redirects_to_the_profile(): void
    {
        $response = $this->post(route('employees.store'), $this->validPayload());

        $employee = Employee::firstOrFail();

        $response->assertRedirect(route('employees.show', $employee->id));
        $this->assertDatabaseHas('employees', [
            'code' => 'EMP-0001',
            'full_name' => 'Ahmad Karimi',
        ]);
    }

    public function test_the_index_lists_employees(): void
    {
        Employee::factory()->count(3)->create(['branch_id' => $this->ctx['branch']->id]);

        $response = $this->get(route('employees.index'));

        $response->assertOk();
        $this->assertCount(3, $response->viewData('page')['props']['employees']['data']);
    }

    public function test_employees_are_scoped_to_the_acting_branch(): void
    {
        Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $otherBranch = Branch::factory()->create(['is_main' => false]);
        Employee::factory()->create(['branch_id' => $otherBranch->id]);

        $response = $this->get(route('employees.index'));

        $this->assertCount(1, $response->viewData('page')['props']['employees']['data']);
    }

    public function test_it_rejects_a_duplicate_code_within_the_branch(): void
    {
        $this->post(route('employees.store'), $this->validPayload());

        $this->post(route('employees.store'), $this->validPayload([
            'first_name' => 'Someone',
            'national_id' => '999',
        ]))->assertSessionHasErrors('code');

        $this->assertSame(1, Employee::count());
    }

    /**
     * Dates that run backwards silently corrupt service length, leave pro-rata
     * and payroll proration, so they are rejected rather than stored.
     */
    public function test_it_rejects_a_probation_end_before_the_joining_date(): void
    {
        $this->post(route('employees.store'), $this->validPayload([
            'probation_end_date' => '2025-12-01',
        ]))->assertSessionHasErrors('probation_end_date');
    }

    public function test_it_requires_a_separation_date_once_the_employee_has_left(): void
    {
        $this->post(route('employees.store'), $this->validPayload([
            'employment_status' => 'resigned',
        ]))->assertSessionHasErrors('separation_date');
    }

    public function test_it_rejects_a_separation_date_while_still_employed(): void
    {
        $this->post(route('employees.store'), $this->validPayload([
            'employment_status' => 'active',
            'separation_date' => '2026-05-01',
        ]))->assertSessionHasErrors('separation_date');
    }

    public function test_an_employee_cannot_report_to_themselves(): void
    {
        $employee = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->patch(route('employees.update', $employee->id), $this->validPayload([
            'code' => $employee->code,
            'reports_to_id' => $employee->id,
        ]))->assertSessionHasErrors('reports_to_id');
    }

    public function test_a_reporting_line_cannot_form_a_loop(): void
    {
        $top = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);
        $middle = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id, 'reports_to_id' => $top->id]);
        $bottom = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id, 'reports_to_id' => $middle->id]);

        // Pointing the top of the chain at the bottom closes the circle.
        $this->patch(route('employees.update', $top->id), $this->validPayload([
            'code' => $top->code,
            'reports_to_id' => $bottom->id,
        ]))->assertSessionHasErrors('reports_to_id');
    }

    public function test_it_soft_deletes_and_restores_through_the_routes(): void
    {
        $employee = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->delete(route('employees.destroy', $employee->id))
            ->assertRedirect(route('employees.index'));
        $this->assertSoftDeleted('employees', ['id' => $employee->id]);

        // The route the Undo toast calls; it derives the name by substitution,
        // so this must exist and accept a trashed model.
        $this->patch(route('employees.restore', $employee->id))
            ->assertRedirect(route('employees.index'));
        $this->assertNotSoftDeleted('employees', ['id' => $employee->id]);
    }

    /**
     * Hard-deleting an employee whose ledger carries posted lines would orphan
     * those vouchers, so it is refused rather than allowed through.
     */
    public function test_force_delete_is_refused_when_the_ledger_has_accounting_history(): void
    {
        $employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
        ]);

        app(\App\Services\TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-08-01',
                'remark' => 'Salary accrual',
            ],
            lines: [
                ['account_id' => $this->ctx['accounts']['permanent-staff-salary']->id, 'debit' => 1000, 'credit' => 0],
                [
                    'account_id' => $this->ctx['accounts']['payroll-liabilities']->id,
                    'ledger_id' => $employee->ledger_id,
                    'debit' => 0,
                    'credit' => 1000,
                ],
            ],
        );

        $employee->delete();

        $this->delete(route('employees.force-delete', $employee->id))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('employees', ['id' => $employee->id]);
        $this->assertNotNull(Ledger::withoutGlobalScopes()->find($employee->ledger_id));
    }

    public function test_force_delete_removes_an_employee_with_no_history(): void
    {
        $employee = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);
        $ledgerId = $employee->ledger_id;
        $employee->delete();

        $this->delete(route('employees.force-delete', $employee->id))
            ->assertRedirect(route('employees.index'));

        $this->assertDatabaseMissing('employees', ['id' => $employee->id]);
        $this->assertNull(Ledger::withoutGlobalScopes()->find($ledgerId));
    }

    public function test_the_employee_search_endpoint_returns_employees(): void
    {
        $employee = Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'first_name' => 'Zahra',
            'last_name' => 'Ahmadi',
        ]);

        $response = $this->getJson('/search/employees?search=Zahra&limit=10');

        $response->assertOk();
        $this->assertContains($employee->id, collect($response->json('data'))->pluck('id')->all());
    }

    /**
     * The picker shape must not leak payroll data into a type-ahead response.
     */
    public function test_the_employee_search_shape_omits_salary_and_identity_fields(): void
    {
        Employee::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'first_name' => 'Zahra',
            'basic_salary' => 99999,
            'national_id' => '1234567890',
        ]);

        $row = $this->getJson('/search/employees?search=Zahra&limit=10')->json('data.0');

        $this->assertArrayNotHasKey('basic_salary', $row);
        $this->assertArrayNotHasKey('national_id', $row);
        $this->assertArrayNotHasKey('bank_account_number', $row);
    }

    public function test_employee_search_excludes_separated_staff_by_default(): void
    {
        $active = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id, 'first_name' => 'Zahra']);
        $gone = Employee::factory()->separated()->create(['branch_id' => $this->ctx['branch']->id, 'first_name' => 'Zahra']);

        $ids = collect($this->getJson('/search/employees?search=Zahra&limit=10')->json('data'))->pluck('id')->all();

        $this->assertContains($active->id, $ids);
        $this->assertNotContains($gone->id, $ids);
    }

    public function test_employee_ledgers_never_appear_in_the_customer_supplier_trash(): void
    {
        $employee = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);
        $employee->delete();

        $trashed = app(\App\Services\DeletedRecordService::class);

        foreach (['ledgers', 'customers', 'suppliers'] as $module) {
            $ids = collect($trashed->indexPayload(['module' => $module, 'per_page' => 100])['records']['data'])
                ->pluck('id')
                ->all();

            $this->assertNotContains(
                $employee->ledger_id,
                $ids,
                "Employee ledger leaked into the [{$module}] trash listing."
            );
        }
    }

    public function test_the_employee_ledger_type_is_employee(): void
    {
        $employee = Employee::factory()->create(['branch_id' => $this->ctx['branch']->id]);

        $this->assertDatabaseHas('ledgers', [
            'id' => $employee->ledger_id,
            'type' => LedgerType::EMPLOYEE->value,
        ]);
    }
}
