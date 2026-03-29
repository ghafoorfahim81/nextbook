<?php

namespace Tests\Feature\Accounting;

use App\Models\Account\Account;
use App\Models\AccountTransfer\AccountTransfer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class AccountTransferFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_it_creates_balanced_account_transfer_transaction(): void
    {
        $toAccount = Account::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'account_type_id' => $this->ctx['account_types']['cash-or-bank']->id,
            'name' => 'Cash In Bank',
            'slug' => 'cash-in-bank-test',
            'number' => '1009',
        ]);

        $response = $this->post(route('account-transfers.store'), [
            'number' => 'AT-001',
            'date' => '2026-03-19',
            'from_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'to_account_id' => $toAccount->id,
            'amount' => 250,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'remark' => 'transfer test',
        ]);

        $response->assertRedirect(route('account-transfers.index'));

        $transfer = AccountTransfer::query()->latest()->firstOrFail();
        $transaction = $transfer->transaction()->firstOrFail();

        $this->assertEquals(2, $transaction->lines()->count());

        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $toAccount->id,
            'debit' => 250.0000,
        ]);

        $this->assertDatabaseHas('transaction_lines', [
            'transaction_id' => $transaction->id,
            'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'credit' => 250.0000,
        ]);
    }
}
