<?php

namespace Tests\Feature\Accounting;

use App\Http\Resources\Account\AccountResource;
use App\Models\Account\Account;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Support\BuildsErpContext;

class AccountStatementTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    #[Test]
    public function it_returns_the_net_account_balance_instead_of_total_credit(): void
    {
        $context = $this->bootstrapErpContext();
        $branch = $context['branch'];
        $currency = $context['currency'];

        $account = Account::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $offsetAccount = Account::factory()->create([
            'branch_id' => $branch->id,
        ]);

        $debitTransaction = Transaction::factory()->create([
            'branch_id' => $branch->id,
            'currency_id' => $currency->id,
            'rate' => 1,
            'status' => 'posted',
        ]);

        TransactionLine::factory()->create([
            'transaction_id' => $debitTransaction->id,
            'account_id' => $account->id,
            'debit' => 1000,
            'credit' => 0,
        ]);

        TransactionLine::factory()->credit(1000)->create([
            'transaction_id' => $debitTransaction->id,
            'account_id' => $offsetAccount->id,
        ]);

        $creditTransaction = Transaction::factory()->create([
            'branch_id' => $branch->id,
            'currency_id' => $currency->id,
            'rate' => 1,
            'status' => 'posted',
        ]);

        TransactionLine::factory()->credit(400)->create([
            'transaction_id' => $creditTransaction->id,
            'account_id' => $account->id,
        ]);

        TransactionLine::factory()->create([
            'transaction_id' => $creditTransaction->id,
            'account_id' => $offsetAccount->id,
            'debit' => 400,
            'credit' => 0,
        ]);

        $statement = $account->fresh()->statement;

        $this->assertSame(1000.0, $statement['total_debit']);
        $this->assertSame(400.0, $statement['total_credit']);
        $this->assertSame(600.0, $statement['balance_amount']);
        $this->assertSame(600.0, $statement['net_balance']);
        $this->assertSame('dr', $statement['balance_nature']);

        $resource = (new AccountResource($account->fresh()))->toArray(request());

        $this->assertSame(600.0, $resource['balance_amount']);
        $this->assertSame('dr', $resource['balance_nature']);
        $this->assertSame(600.0, $resource['net_balance']);
    }
}
