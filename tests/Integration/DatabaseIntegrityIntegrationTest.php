<?php

namespace Tests\Integration;

use App\Models\Purchase\Purchase;
use App\Models\Sale\Sale;
use App\Models\Transaction\Transaction;
use App\Models\Transaction\TransactionLine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class DatabaseIntegrityIntegrationTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_foreign_keys_reject_invalid_transaction_line_references(): void
    {
        $transaction = Transaction::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'date' => '2026-03-19',
        ]);

        $this->expectException(QueryException::class);

        TransactionLine::query()->create([
            'transaction_id' => $transaction->id,
            'account_id' => (string) Str::ulid(),
            'ledger_id' => null,
            'debit' => 10,
            'credit' => 0,
        ]);
    }

    public function test_purchase_and_sale_support_soft_deletes(): void
    {
        $purchase = Purchase::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'type' => 'cash',
        ]);

        $sale = Sale::factory()->create([
            'branch_id' => $this->ctx['branch']->id,
            'customer_id' => $this->ctx['customer_ledger']->id,
            'type' => 'cash',
        ]);

        $purchase->delete();
        $sale->delete();

        $this->assertSoftDeleted('purchases', ['id' => $purchase->id]);
        $this->assertSoftDeleted('sales', ['id' => $sale->id]);
        $this->assertDatabaseMissing('purchases', ['id' => $purchase->id, 'deleted_at' => null]);
        $this->assertDatabaseMissing('sales', ['id' => $sale->id, 'deleted_at' => null]);
    }
}
