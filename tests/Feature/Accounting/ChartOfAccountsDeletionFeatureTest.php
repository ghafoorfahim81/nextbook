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

    public function test_it_deletes_an_account_and_opening_transaction_when_opening_is_the_only_transaction(): void
    {
        $account = $this->makeAccount();
        $openingTransaction = $this->createOpeningTransaction($account, 125);

        $response = $this->delete(route('chart-of-accounts.destroy', $account));

        $response->assertRedirect(route('chart-of-accounts.index'));
        $response->assertSessionHas('success');

        $this->assertSoftDeleted('accounts', ['id' => $account->id]);
        $this->assertSoftDeleted('ledger_openings', [
            'ledgerable_id' => $account->id,
            'ledgerable_type' => $account->getMorphClass(),
            'transaction_id' => $openingTransaction->id,
        ]);
        $this->assertSoftDeleted('transactions', ['id' => $openingTransaction->id]);
        $this->assertEquals(
            2,
            Transaction::withTrashed()
                ->findOrFail($openingTransaction->id)
                ->lines()
                ->withTrashed()
                ->count()
        );
        $this->assertSoftDeleted('transaction_lines', [
            'transaction_id' => $openingTransaction->id,
            'account_id' => $account->id,
        ]);
        $this->assertSoftDeleted('transaction_lines', [
            'transaction_id' => $openingTransaction->id,
            'account_id' => $this->ctx['accounts']['opening-balance-equity']->id,
        ]);
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
