<?php

namespace Tests\Feature\Accounting;

use App\Enums\TransactionStatus;
use App\Models\Payment\Payment;
use App\Models\Receipt\Receipt;
use App\Models\Transaction\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Saving a receipt or a payment as a draft.
 *
 * A draft voucher carries NO journal lines on purpose: the voucher and the
 * invoices it will relieve are parked on posting_payload, and the journal is
 * built only when it is posted — claiming an open invoice before the money is
 * real would close one nobody has paid.
 *
 * TransactionService::post() required at least one line from every caller, so
 * with "post immediately" turned off, saving any receipt or payment threw
 * "Transaction must have at least one line" and returned a 500. Nothing
 * covered the draft path, which is why it stayed broken.
 */
class DraftVoucherSaveTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
        $this->actingAs($this->ctx['user']);

        // The preference the two forms read. Off means "save as draft".
        set_user_preference('transaction.receipt_post_immediately', false, $this->ctx['user']);
        set_user_preference('transaction.payment_post_immediately', false, $this->ctx['user']);
    }

    public function test_a_receipt_can_be_saved_as_a_draft(): void
    {
        $this->post(route('receipts.store'), [
            'number' => 21,
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => 750,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'saved for later',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $receipt = Receipt::query()->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $receipt->status);

        $draft = Transaction::query()->where('reference_id', $receipt->id)->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $draft->status);

        // Nothing in the ledger yet, and the voucher kept for posting day.
        $this->assertSame(0, $draft->lines()->count(), 'A draft must not touch the ledger.');
        $this->assertNotEmpty(
            data_get($draft->posting_payload, 'settlement_voucher'),
            'The draft should carry the voucher it will post.',
        );
    }

    public function test_a_payment_can_be_saved_as_a_draft(): void
    {
        $this->post(route('payments.store'), [
            'number' => 31,
            'ledger_id' => $this->ctx['supplier_ledger']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => 400,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'narration' => 'saved for later',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $payment = Payment::query()->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $payment->status);

        $draft = Transaction::query()->where('reference_id', $payment->id)->firstOrFail();
        $this->assertSame(TransactionStatus::DRAFT->value, $draft->status);
        $this->assertSame(0, $draft->lines()->count());
    }

    /**
     * The exemption is for the EMPTY case only. A posted entry with no lines
     * is still nonsense and must be refused — otherwise this fix would have
     * opened a hole wider than the bug it closed.
     */
    public function test_a_posted_entry_still_needs_lines(): void
    {
        $this->expectExceptionMessage('Transaction must have at least one line');

        app(\App\Services\TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => now()->toDateString(),
                'status' => TransactionStatus::POSTED->value,
                'branch_id' => $this->ctx['branch']->id,
            ],
            lines: [],
        );
    }

    /** And a draft that does carry lines is held to the same balancing rules. */
    public function test_a_draft_with_unbalanced_lines_is_still_refused(): void
    {
        $this->expectException(\Throwable::class);

        app(\App\Services\TransactionService::class)->post(
            header: [
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'date' => now()->toDateString(),
                'status' => TransactionStatus::DRAFT->value,
                'branch_id' => $this->ctx['branch']->id,
            ],
            lines: [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'debit' => 100,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['account-receivable']->id,
                    'debit' => 0,
                    'credit' => 55,
                ],
            ],
        );
    }

    public function test_a_drafted_receipt_can_then_be_posted(): void
    {
        $this->post(route('receipts.store'), [
            'number' => 22,
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'date' => now()->toDateString(),
            'amount' => 750,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $receipt = Receipt::query()->firstOrFail();

        $this->post(route('receipts.post', $receipt->id))->assertRedirect();

        $receipt->refresh();
        $this->assertSame(TransactionStatus::POSTED->value, $receipt->status);

        $posted = Transaction::query()->where('reference_id', $receipt->id)->firstOrFail();
        $this->assertGreaterThan(0, $posted->lines()->count(), 'Posting should build the journal.');
    }
}
