<?php

namespace Tests\Feature\Accounting;

use App\Models\Ledger\Ledger;
use App\Models\Payment\Payment;
use App\Models\Receipt\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Why a payment may name a customer, while a purchase may not.
 *
 * The two look like the same permission from the outside and are not. Paying a
 * customer is a refund: it moves money on the receivable the customer already
 * has, and never leaves the receivable account. Buying from a customer would
 * create a payable under a ledger whose control account is the receivable —
 * the debt that then exists in the ledger and is reachable from nowhere.
 *
 * This holds that line: the refund stays on its own control account.
 */
class RefundStaysOnItsOwnAccountTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);
    }

    /** @return array<string, float> slug => debit-minus-credit */
    private function accountsTouchedBy(Ledger $ledger): array
    {
        return DB::table('transaction_lines as tl')
            ->join('transactions as t', 't.id', '=', 'tl.transaction_id')
            ->join('accounts as a', 'a.id', '=', 'tl.account_id')
            ->where('tl.ledger_id', $ledger->id)
            ->whereIn('t.status', ['posted', 'reversed'])
            ->groupBy('a.slug')
            ->selectRaw('a.slug, COALESCE(SUM(tl.debit - tl.credit), 0) AS balance')
            ->pluck('balance', 'a.slug')
            ->map(fn ($value) => (float) $value)
            ->all();
    }

    public function test_paying_a_customer_stays_on_the_receivable_account(): void
    {
        $customer = $this->ctx['customer_ledger'];

        $this->post(route('payments.store'), [
            'number' => 11,
            'ledger_id' => $customer->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => 500,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'refunding an overpayment',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $touched = $this->accountsTouchedBy($customer);

        $this->assertArrayNotHasKey(
            'account-payable',
            $touched,
            'A refund to a customer must never create a payable under a customer ledger.',
        );

        $this->assertNotEmpty($touched, 'The refund should have moved the customer ledger.');

        // With nothing outstanding to refund against, the money parks on
        // Customer Advances — a debit there reads as "refunded beyond what was
        // owed", which is unusual but legible. What matters is that it is
        // still a receivable-side account: the customer relationship never
        // spills onto the payable side.
        foreach (array_keys($touched) as $slug) {
            $this->assertTrue(
                in_array($slug, ['account-receivable', 'customer-advances'], true),
                'Every line under a customer ledger belongs on a receivable-side account; saw ' . $slug,
            );
        }
    }

    public function test_receiving_from_a_supplier_stays_on_the_payable_account(): void
    {
        $supplier = $this->ctx['supplier_ledger'];

        $this->post(route('receipts.store'), [
            'number' => 12,
            'ledger_id' => $supplier->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => 400,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'supplier returning an advance',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $touched = $this->accountsTouchedBy($supplier);

        $this->assertArrayNotHasKey(
            'account-receivable',
            $touched,
            'Money back from a supplier must never create a receivable under a supplier ledger.',
        );

        $this->assertNotEmpty($touched);

        foreach (array_keys($touched) as $slug) {
            $this->assertTrue(
                str_contains($slug, 'payable') || str_contains($slug, 'advance'),
                'Every line under a supplier ledger belongs on a payable or advance account; saw ' . $slug,
            );
        }
    }
}
