<?php

namespace Tests\Feature\Hr;

use App\Enums\LedgerType;
use App\Models\Ledger\Ledger;
use App\Services\Accounting\SettlementService;
use App\Support\BranchContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Guards the blast radius of adding EMPLOYEE to LedgerType.
 *
 * Before employees became ledger parties, every ledger in the system was a
 * customer or a supplier, and a lot of code took that for granted: type filters
 * that only applied when a caller remembered to pass one, and two-way ternaries
 * that quietly treated "not a supplier" as "a customer". Those are not the kind
 * of defect that throws — an employee simply appears in a customer dropdown, or
 * a salary posts to Accounts Receivable.
 *
 * Every assertion here is a leak that shipped code would not otherwise catch.
 */
class LedgerTypeIsolationFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Ledger $employeeLedger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->ctx = $this->bootstrapErpContext();

        $this->employeeLedger = Ledger::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'name' => 'Ahmad Karimi (EMP-001)',
            'code' => 'EMP-001',
            'type' => LedgerType::EMPLOYEE->value,
            'is_active' => true,
        ]);
    }

    public function test_the_database_accepts_the_employee_ledger_type(): void
    {
        $this->assertDatabaseHas('ledgers', [
            'id' => $this->employeeLedger->id,
            'type' => LedgerType::EMPLOYEE->value,
        ]);
    }

    public function test_the_check_constraint_still_rejects_an_unknown_type(): void
    {
        $this->expectException(\Illuminate\Database\QueryException::class);

        DB::table('ledgers')
            ->where('id', $this->employeeLedger->id)
            ->update(['type' => 'vendor']);
    }

    /**
     * The single highest-impact leak: most ledger pickers in the app send no
     * type at all, so an unfiltered default would list every employee.
     */
    public function test_ledger_search_without_a_type_excludes_employees(): void
    {
        // Searching the employee's own name: a term that unambiguously matches
        // them, so an empty result proves the filter and not a weak query.
        $response = $this->getJson('/search/ledgers?search=Ahmad&limit=50');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertNotContains($this->employeeLedger->id, $ids);
    }

    public function test_ledger_search_without_a_type_still_returns_commercial_parties(): void
    {
        $response = $this->getJson('/search/ledgers?search=Ledger&limit=50');

        $response->assertOk();

        $ids = collect($response->json('data'))->pluck('id')->all();

        $this->assertContains($this->ctx['customer_ledger']->id, $ids);
        $this->assertContains($this->ctx['supplier_ledger']->id, $ids);
    }

    public function test_ledger_search_can_opt_into_employees_explicitly(): void
    {
        $response = $this->getJson(
            '/search/ledgers?search=Ahmad&limit=50&types[]=customer&types[]=supplier&types[]=employee'
        );

        $response->assertOk();

        $this->assertContains(
            $this->employeeLedger->id,
            collect($response->json('data'))->pluck('id')->all()
        );
    }

    /**
     * An unrecognised type must not widen the result set. Falling open here
     * would turn a typo in a Vue prop into an employee-data leak.
     */
    public function test_ledger_search_falls_back_to_commercial_types_on_a_junk_filter(): void
    {
        $response = $this->getJson('/search/ledgers?search=Ahmad&limit=50&types[]=nonsense');

        $response->assertOk();

        $this->assertNotContains(
            $this->employeeLedger->id,
            collect($response->json('data'))->pluck('id')->all()
        );
    }

    public function test_the_generic_ledger_index_cannot_be_pointed_at_employees(): void
    {
        $response = $this->get('/ledgers?type=employee');

        $response->assertOk();

        $ids = collect($response->viewData('page')['props']['ledgers']['data'] ?? [])
            ->pluck('id')
            ->all();

        $this->assertNotContains($this->employeeLedger->id, $ids);
    }

    /**
     * The dangerous one. partyAccountSlug() used to be a two-way ternary that
     * routed anything that was not a supplier to Accounts Receivable — so a
     * salary disbursement would have posted against receivables, silently,
     * and openItems() would have matched nothing.
     */
    public function test_settlement_routes_an_employee_to_payroll_liabilities(): void
    {
        $slugFor = function (Ledger $ledger): string {
            $method = new ReflectionMethod(SettlementService::class, 'partyAccountSlug');
            $method->setAccessible(true);

            return $method->invoke(app(SettlementService::class), $ledger);
        };

        $this->assertSame('payroll-liabilities', $slugFor($this->employeeLedger));
        $this->assertSame('account-receivable', $slugFor($this->ctx['customer_ledger']));
        $this->assertSame('account-payable', $slugFor($this->ctx['supplier_ledger']));
    }

    public function test_settlement_parks_employee_overpayment_in_employee_advances(): void
    {
        $slugFor = function (Ledger $ledger): string {
            $method = new ReflectionMethod(SettlementService::class, 'advanceAccountSlug');
            $method->setAccessible(true);

            return $method->invoke(app(SettlementService::class), $ledger);
        };

        $this->assertSame('employee-advances', $slugFor($this->employeeLedger));
        $this->assertSame('customer-advances', $slugFor($this->ctx['customer_ledger']));
        $this->assertSame('supplier-advances', $slugFor($this->ctx['supplier_ledger']));
    }

    public function test_payroll_control_accounts_resolve_by_slug(): void
    {
        BranchContext::flush($this->ctx['branch']->id);

        foreach (['payroll-liabilities', 'salary-tax-payable', 'employee-advances', 'employee-loans-receivable'] as $slug) {
            $this->assertNotNull(
                BranchContext::glAccount($slug, $this->ctx['branch']->id),
                "Expected branch to resolve the [{$slug}] account by slug."
            );
        }
    }

    /**
     * Employees are credit-normal like suppliers: an accrued but undisbursed
     * salary is money the company owes. The accessor previously compared the
     * LedgerType enum to a raw string with ===, which is always false, so this
     * also pins the supplier arm that had silently stopped firing.
     */
    public function test_statement_treats_employees_as_credit_normal_parties(): void
    {
        $this->assertSame('cr', $this->employeeLedger->statement['normal_balance_nature']);
        $this->assertSame('cr', $this->ctx['supplier_ledger']->statement['normal_balance_nature']);
        $this->assertSame('dr', $this->ctx['customer_ledger']->statement['normal_balance_nature']);
    }

    public function test_statement_meaning_names_the_employee_rather_than_a_customer(): void
    {
        $this->seedLedgerCredit($this->employeeLedger, 5000);

        $statement = $this->employeeLedger->fresh()->statement;

        $this->assertStringContainsString('employee', strtolower($statement['meaning']));
        $this->assertSame('cr', $statement['balance_nature']);
        $this->assertSame(5000.0, $statement['payable_amount']);
        $this->assertSame('-', $statement['balance_type']);
    }

    /**
     * Post a payroll-shaped credit onto the employee's control account so the
     * statement has something real to read.
     */
    private function seedLedgerCredit(Ledger $ledger, float $amount): void
    {
        app(\App\Services\TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-08-01',
                'remark' => 'Salary accrual probe',
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['permanent-staff-salary']->id,
                    'debit' => $amount,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['payroll-liabilities']->id,
                    'ledger_id' => $ledger->id,
                    'debit' => 0,
                    'credit' => $amount,
                ],
            ],
        );
    }
}
