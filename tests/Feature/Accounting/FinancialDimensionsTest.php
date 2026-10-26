<?php

namespace Tests\Feature\Accounting;

use App\Models\Accounting\AllocationRule;
use App\Models\Accounting\CostCenter;
use App\Models\Administration\Branch;
use App\Models\Expense\Expense;
use App\Models\Expense\ExpenseCategory;
use App\Models\Transaction\TransactionLine;
use App\Services\ReportService;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Phase 7: cost centers, and a shared expense split across branches by a
 * dated allocation rule.
 *
 * Head-office rent of 30,000 paid in Kabul, split 50% Kabul, 30% Herat, 20%
 * Mazar: each branch carries its part, the payment stays in Kabul, and the
 * Inter-branch Clearing account keeps every branch's books balanced.
 */
class FinancialDimensionsTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private Branch $kabul;

    private Branch $herat;

    private Branch $mazar;

    private ExpenseCategory $category;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $this->kabul = $this->ctx['branch'];
        $this->herat = Branch::factory()->create(['company_id' => $this->ctx['company']->id, 'name' => 'Herat']);
        $this->mazar = Branch::factory()->create(['company_id' => $this->ctx['company']->id, 'name' => 'Mazar']);
        $this->category = ExpenseCategory::create(['name' => 'Rent', 'is_active' => true]);
    }

    public function test_a_cost_center_is_created_and_listed(): void
    {
        $this->post(route('cost-centers.store'), ['name' => 'Head office', 'code' => 'HO', 'is_active' => true])
            ->assertSessionHasNoErrors();

        $center = CostCenter::query()->where('name', 'Head office')->firstOrFail();
        $this->post(route('cost-centers.store'), ['name' => 'Marketing', 'parent_id' => $center->id, 'is_active' => true])
            ->assertSessionHasNoErrors();

        $this->get(route('cost-centers.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Accounting/CostCenters/Index')->has('costCenters.data', 2));

        // A name is unique in the company.
        $this->post(route('cost-centers.store'), ['name' => 'Head office'])->assertSessionHasErrors('name');
    }

    public function test_a_split_must_add_up_to_100_percent(): void
    {
        $this->post(route('allocation-rules.store'), [
            'name' => 'Head office rent',
            'effective_from' => '2026-01-01',
            'shares' => [
                ['branch_id' => $this->kabul->id, 'percentage' => 50],
                ['branch_id' => $this->herat->id, 'percentage' => 30],
            ],
        ])->assertSessionHasErrors('shares');

        $this->assertSame(0, AllocationRule::query()->count());
    }

    public function test_a_shared_expense_is_split_across_branches_and_each_branch_balances(): void
    {
        $rule = $this->rule(['2026-01-01' => [50, 30, 20]]);
        $headOffice = CostCenter::create(['name' => 'Head office', 'is_active' => true]);

        $expense = $this->postExpense(30000, '2026-03-10', $rule, $headOffice);
        $lines = $expense->transaction->lines;

        // Each branch carries its part of the rent, on the cost center.
        $rent = $lines->where('account_id', $this->ctx['accounts']['other-expenses']->id);
        $this->assertEqualsCanonicalizing(
            [[$this->kabul->id, 15000.0], [$this->herat->id, 9000.0], [$this->mazar->id, 6000.0]],
            $rent->map(fn ($line) => [$line->branch_id, (float) $line->debit])->values()->all(),
        );
        $this->assertTrue($rent->every(fn ($line) => $line->cost_center_id === $headOffice->id));

        // The whole payment leaves Kabul's cash.
        $cash = $lines->where('account_id', $this->ctx['accounts']['cash-in-hand']->id);
        $this->assertSame(30000.0, (float) $cash->sum('credit'));
        $this->assertTrue($cash->every(fn ($line) => $line->branch_id === null));

        // Every branch's books balance on their own, and clearing nets to nil.
        foreach ([$this->kabul, $this->herat, $this->mazar] as $branch) {
            $this->assertSame(0.0, $this->branchImbalance($expense, $branch->id), "{$branch->name} does not balance.");
        }
        $clearing = $lines->where('account_id', $this->ctx['accounts']['inter-branch-clearing']->id);
        $this->assertSame(0.0, round((float) $clearing->sum('debit') - (float) $clearing->sum('credit'), 4));

        // The expense page lists the three parts.
        $resource = (new \App\Http\Resources\Expense\ExpenseResource($expense->fresh(['transaction.lines'])))->toArray(request());
        $this->assertCount(3, $resource['allocation']);
    }

    public function test_a_later_version_of_the_split_leaves_earlier_expenses_alone(): void
    {
        $rule = $this->rule([
            '2026-01-01' => [50, 30, 20],
            '2026-06-01' => [70, 20, 10],
        ]);

        $march = $this->postExpense(1000, '2026-03-01', $rule);
        $july = $this->postExpense(1000, '2026-07-01', $rule);

        $this->assertSame(300.0, $this->partFor($march, $this->herat->id));
        $this->assertSame(200.0, $this->partFor($july, $this->herat->id));
    }

    public function test_rounding_leaves_the_remainder_on_the_last_branch(): void
    {
        $rule = $this->rule(['2026-01-01' => [33.3333, 33.3333, 33.3334]]);

        $expense = $this->postExpense(100, '2026-02-01', $rule);

        $parts = [$this->partFor($expense, $this->kabul->id), $this->partFor($expense, $this->herat->id), $this->partFor($expense, $this->mazar->id)];
        $this->assertSame([33.33, 33.33, 33.34], $parts);
    }

    public function test_an_expense_before_the_first_version_is_refused(): void
    {
        $rule = $this->rule(['2026-06-01' => [50, 30, 20]]);

        $this->post(route('expenses.store'), $this->expensePayload(1000, '2026-01-15', $rule))
            ->assertSessionHasErrors('allocation_rule_id');

        $this->assertSame(0, Expense::query()->count());
    }

    public function test_reversing_a_split_expense_undoes_every_branch_part(): void
    {
        $rule = $this->rule(['2026-01-01' => [50, 30, 20]]);
        $expense = $this->postExpense(30000, '2026-03-10', $rule);

        $this->post(route('expenses.reverse', $expense), ['reason' => 'wrong month'])->assertSessionHasNoErrors();

        $herat = TransactionLine::query()
            ->where('account_id', $this->ctx['accounts']['other-expenses']->id)
            ->where('branch_id', $this->herat->id)
            ->get();
        $this->assertSame(0.0, round((float) $herat->sum('debit') - (float) $herat->sum('credit'), 4));
    }

    public function test_a_journal_line_keeps_its_cost_center(): void
    {
        $center = CostCenter::create(['name' => 'Transport', 'is_active' => true]);

        $transaction = app(TransactionService::class)->post(
            header: ['currency_id' => $this->ctx['currency']->id, 'rate' => 1, 'date' => '2026-03-01', 'remark' => 'Fuel'],
            lines: [
                ['account_id' => $this->ctx['accounts']['other-expenses']->id, 'debit' => 500, 'credit' => 0, 'cost_center_id' => $center->id],
                ['account_id' => $this->ctx['accounts']['cash-in-hand']->id, 'debit' => 0, 'credit' => 500],
            ],
        );

        $this->assertSame($center->id, $transaction->lines->firstWhere('debit', '>', 0)->cost_center_id);
    }

    public function test_the_branch_comparison_gives_each_branch_its_share(): void
    {
        $rule = $this->rule(['2026-01-01' => [50, 30, 20]]);
        $this->postExpense(30000, '2026-03-10', $rule);

        $result = app(ReportService::class)->getReportData([
            'report' => 'branch_comparison',
            'branch_id' => $this->kabul->id,
            'company_id' => $this->ctx['company']->id,
            'date_from' => '2026-01-01',
            'date_to' => '2026-12-31',
        ]);

        $expenses = collect($result['rows'])->pluck('expenses', 'branch');
        $this->assertSame(15000.0, $expenses[$this->kabul->name]);
        $this->assertSame(9000.0, $expenses['Herat']);
        $this->assertSame(6000.0, $expenses['Mazar']);
    }

    /** @param array<string, array{0: float, 1: float, 2: float}> $versions Kabul, Herat, Mazar */
    private function rule(array $versions): AllocationRule
    {
        $rule = AllocationRule::create(['name' => 'Head office rent', 'is_active' => true]);

        foreach ($versions as $date => [$kabul, $herat, $mazar]) {
            foreach ([[$this->kabul, $kabul], [$this->herat, $herat], [$this->mazar, $mazar]] as [$branch, $percentage]) {
                $rule->shares()->create([
                    'company_id' => $rule->company_id,
                    'branch_id' => $branch->id,
                    'effective_from' => $date,
                    'percentage' => $percentage,
                ]);
            }
        }

        return $rule;
    }

    private function expensePayload(float $amount, string $date, ?AllocationRule $rule = null, ?CostCenter $center = null): array
    {
        return [
            'date' => $date,
            'category_id' => $this->category->id,
            'expense_account_id' => $this->ctx['accounts']['other-expenses']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'cost_center_id' => $center?->id,
            'allocation_rule_id' => $rule?->id,
            'details' => [['title' => 'Rent', 'amount' => $amount]],
        ];
    }

    private function postExpense(float $amount, string $date, ?AllocationRule $rule = null, ?CostCenter $center = null): Expense
    {
        $this->post(route('expenses.store'), $this->expensePayload($amount, $date, $rule, $center))
            ->assertSessionHasNoErrors();

        return Expense::query()->latest('created_at')->latest('id')->firstOrFail()->load('transaction.lines');
    }

    private function partFor(Expense $expense, string $branchId): float
    {
        return (float) $expense->transaction->lines
            ->where('account_id', $this->ctx['accounts']['other-expenses']->id)
            ->firstWhere('branch_id', $branchId)
            ?->debit;
    }

    /** Debits minus credits of one branch's lines (a line without a branch is the voucher's). */
    private function branchImbalance(Expense $expense, string $branchId): float
    {
        $lines = $expense->transaction->lines->filter(
            fn ($line) => ($line->branch_id ?? $expense->transaction->branch_id) === $branchId
        );

        return round((float) $lines->sum('debit') - (float) $lines->sum('credit'), 4);
    }
}
