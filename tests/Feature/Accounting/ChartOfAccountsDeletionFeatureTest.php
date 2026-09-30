<?php

namespace Tests\Feature\Accounting;

use App\Models\Account\Account;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Administration\Currency;
use App\Models\Role;
use App\Models\Transaction\Transaction;
use App\Models\User;
use App\Services\TransactionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class ChartOfAccountsDeletionFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();

        $user = User::factory()->create([
            'name' => 'account-delete-admin',
            'email' => 'account-delete-admin@example.test',
            'preferences' => User::DEFAULT_PREFERENCES,
        ]);

        $role = Role::query()->firstOrCreate(
            ['name' => 'super-admin', 'guard_name' => 'web'],
            ['slug' => 'super-admin']
        );

        if ($role->slug !== 'super-admin') {
            $role->slug = 'super-admin';
            $role->save();
        }

        $user->assignRole($role);
        $this->actingAs($user);

        $branch = Branch::factory()->create([
            'name' => 'Main Branch '.fake()->unique()->numberBetween(100, 999),
            'is_main' => true,
        ]);

        $currency = Currency::factory()->create([
            'branch_id' => $branch->id,
            'name' => 'Afghani',
            'code' => 'AFN',
            'symbol' => 'Af',
            'exchange_rate' => 1,
            'is_active' => true,
            'is_base_currency' => true,
        ]);

        $company = Company::factory()->create([
            'name_en' => 'Account Delete Test Company',
            'currency_id' => $currency->id,
        ]);

        $user->update([
            'branch_id' => $branch->id,
            'company_id' => $company->id,
        ]);

        $user->refresh();
        $this->actingAs($user);
        app()->instance('active_branch_id', $branch->id);

        $accountTypes = $this->createDefaultAccountTypes($branch->id);
        $accounts = $this->createDefaultGlAccounts($branch->id, $accountTypes);

        $this->ctx = [
            'user' => $user,
            'branch' => $branch,
            'company' => $company,
            'currency' => $currency,
            'account_types' => $accountTypes,
            'accounts' => $accounts,
        ];
    }

    public function test_it_deletes_an_account_with_no_transactions(): void
    {
        $account = $this->makeAccount();

        $response = $this->delete(route('chart-of-accounts.destroy', $account));

        $response->assertRedirect(route('chart-of-accounts.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
    }

    public function test_it_does_not_delete_an_account_that_has_an_opening_balance(): void
    {
        $account = $this->makeAccount();
        $openingTransaction = $this->createOpeningTransaction($account, 125);

        $response = $this->delete(route('chart-of-accounts.destroy', $account));

        $response->assertRedirect(route('chart-of-accounts.index'));
        $response->assertSessionHas('error');

        $this->assertNotSoftDeleted('accounts', ['id' => $account->id]);
        $this->assertNotSoftDeleted('ledger_openings', [
            'ledgerable_id' => $account->id,
            'ledgerable_type' => $account->getMorphClass(),
            'transaction_id' => $openingTransaction->id,
        ]);
        $this->assertNotSoftDeleted('transactions', ['id' => $openingTransaction->id]);
    }

    public function test_it_posts_a_credit_opening_on_the_credit_side(): void
    {
        $response = $this->post(route('chart-of-accounts.store'), $this->accountPayload('account-payable', [
            'transaction_type' => 'credit',
            'amount' => 300,
        ]));

        $response->assertRedirect(route('chart-of-accounts.index'));
        $account = Account::where('number', '77001')->firstOrFail();
        $lines = $account->opening->transaction->lines;

        $this->assertEquals(300, (float) $lines->firstWhere('account_id', $account->id)->credit);
        $this->assertEquals(0, (float) $lines->firstWhere('account_id', $account->id)->debit);
        $this->assertEquals(300, (float) $lines->firstWhere('account_id', $this->ctx['accounts']['opening-balance-equity']->id)->debit);
    }

    public function test_it_posts_a_debit_opening_by_default(): void
    {
        $this->post(route('chart-of-accounts.store'), $this->accountPayload('other-current-asset', ['amount' => 80]));

        $account = Account::where('number', '77001')->firstOrFail();
        $line = $account->opening->transaction->lines->firstWhere('account_id', $account->id);

        $this->assertEquals(80, (float) $line->debit);
    }

    public function test_it_skips_the_opening_for_income_expense_and_cogs_accounts(): void
    {
        foreach (['income', 'expense', 'cost-of-goods-sold'] as $i => $slug) {
            $number = (string) (77001 + $i);
            $this->post(route('chart-of-accounts.store'), array_merge(
                $this->accountPayload($slug, ['amount' => 50]),
                ['name' => 'No Opening '.$slug, 'number' => $number],
            ))->assertSessionHasNoErrors();

            $this->assertNull(Account::where('number', $number)->firstOrFail()->opening, $slug);
        }
    }

    public function test_it_updates_the_opening_side(): void
    {
        $this->post(route('chart-of-accounts.store'), $this->accountPayload('other-current-asset', ['amount' => 80]));
        $account = Account::where('number', '77001')->firstOrFail();

        $this->patch(route('chart-of-accounts.update', $account), $this->accountPayload('other-current-asset', [
            'transaction_type' => 'credit',
            'amount' => 90,
        ]))->assertSessionHasNoErrors();

        $line = $account->fresh()->opening->transaction->lines->firstWhere('account_id', $account->id);
        $this->assertEquals(90, (float) $line->credit);
        $this->assertEquals(0, (float) $line->debit);
    }

    private function accountPayload(string $typeSlug, array $overrides = []): array
    {
        return array_merge([
            'name' => 'Opening Test Account',
            'number' => '77001',
            'account_type_id' => $this->ctx['account_types'][$typeSlug]->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'amount' => 0,
        ], $overrides);
    }

    public function test_it_does_not_delete_an_account_that_has_non_opening_transactions(): void
    {
        $account = $this->makeAccount();
        $openingTransaction = $this->createOpeningTransaction($account, 125);
        $nonOpeningTransaction = $this->createNonOpeningTransaction($account, 50);

        $response = $this->delete(route('chart-of-accounts.destroy', $account));

        $response->assertRedirect(route('chart-of-accounts.index'));
        $response->assertSessionHas('error');

        $this->assertNotSoftDeleted('accounts', ['id' => $account->id]);
        $this->assertNotSoftDeleted('ledger_openings', [
            'ledgerable_id' => $account->id,
            'ledgerable_type' => $account->getMorphClass(),
            'transaction_id' => $openingTransaction->id,
        ]);
        $this->assertNotSoftDeleted('transactions', ['id' => $openingTransaction->id]);
        $this->assertNotSoftDeleted('transactions', ['id' => $nonOpeningTransaction->id]);
    }

    private function makeAccount(): Account
    {
        return Account::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'account_type_id' => $this->ctx['account_types']['expense']->id,
            'name' => 'Deletable Account '.fake()->unique()->numberBetween(100, 999),
            'number' => (string) fake()->unique()->numberBetween(10000, 99999),
            'slug' => 'deletable-account-'.fake()->unique()->numberBetween(100, 999),
            'is_main' => false,
            'is_active' => true,
        ]);
    }

    private function createOpeningTransaction(Account $account, float $amount): Transaction
    {
        $transaction = app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-04-01',
                'reference_type' => Account::class,
                'reference_id' => $account->id,
                'remark' => 'Opening balance for account '.$account->name,
            ],
            lines: [
                [
                    'account_id' => $account->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'remark' => 'Opening balance for account '.$account->name,
                ],
                [
                    'account_id' => $this->ctx['accounts']['opening-balance-equity']->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'remark' => 'Opening balance for account '.$account->name,
                ],
            ],
        );

        $account->opening()->create(['transaction_id' => $transaction->id]);

        return $transaction;
    }

    private function createNonOpeningTransaction(Account $account, float $amount): Transaction
    {
        return app(TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-04-02',
                'remark' => 'Non-opening transaction for account '.$account->name,
            ],
            lines: [
                [
                    'account_id' => $account->id,
                    'debit' => $amount,
                    'credit' => 0,
                    'remark' => 'Regular transaction for account '.$account->name,
                ],
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'debit' => 0,
                    'credit' => $amount,
                    'remark' => 'Regular transaction for account '.$account->name,
                ],
            ],
        );
    }
}
