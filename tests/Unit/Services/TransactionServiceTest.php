<?php

namespace Tests\Unit\Services;

use App\Services\TransactionService;
use Exception;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class TransactionServiceTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_it_posts_balanced_transactions_and_persists_lines(): void
    {
        $service = app(TransactionService::class);

        $transaction = $service->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-19',
                'reference_type' => 'unit-test',
                'reference_id' => $this->ctx['item']->id,
                'remark' => 'balanced entry',
                'status' => 'posted',
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'debit' => 150,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'debit' => 0,
                    'credit' => 150,
                ],
            ],
        );

        $this->assertDatabaseHas('transactions', [
            'id' => $transaction->id,
            'reference_type' => 'unit-test',
            'status' => 'posted',
        ]);

        $this->assertEquals(2, $transaction->lines()->count());
        $this->assertEquals(150.0, (float) $transaction->lines()->sum('debit'));
        $this->assertEquals(150.0, (float) $transaction->lines()->sum('credit'));
    }

    public function test_it_rejects_unbalanced_transactions(): void
    {
        $service = app(TransactionService::class);

        $this->expectException(Exception::class);
        $this->expectExceptionMessage('Transaction is not balanced');

        $service->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-19',
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'debit' => 0,
                    'credit' => 95,
                ],
            ],
        );
    }

    public function test_it_reverses_posted_transactions_with_swapped_lines(): void
    {
        $service = app(TransactionService::class);

        $original = $service->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => '2026-03-19',
                'reference_type' => 'reverse-test',
                'reference_id' => $this->ctx['item']->id,
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'ledger_id' => $this->ctx['customer_ledger']->id,
                    'debit' => 200,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'debit' => 0,
                    'credit' => 200,
                ],
            ],
        );

        $reversal = $service->reverse($original, 'testing reversal');

        $original->refresh();

        $this->assertEquals('reversed', $original->status);
        $this->assertEquals(2, $reversal->lines()->count());
        $this->assertEquals(200.0, (float) $reversal->lines()->sum('debit'));
        $this->assertEquals(200.0, (float) $reversal->lines()->sum('credit'));
    }
}
