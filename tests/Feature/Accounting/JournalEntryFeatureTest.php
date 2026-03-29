<?php

namespace Tests\Feature\Accounting;

use App\Models\JournalEntry\JournalEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class JournalEntryFeatureTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    public function test_it_creates_a_journal_entry_and_posts_balanced_transaction_lines(): void
    {
        $response = $this->post(route('journal-entries.store'), [
            'number' => 1001,
            'date' => '2026-03-19',
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'remarks' => 'feature journal',
            'lines' => [
                [
                    'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                    'debit' => 500,
                    'credit' => 0,
                ],
                [
                    'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                    'debit' => 0,
                    'credit' => 500,
                ],
            ],
        ]);

        $response->assertRedirect(route('journal-entries.index'));

        $journal = JournalEntry::query()->latest()->firstOrFail();

        $this->assertDatabaseHas('transactions', [
            'reference_type' => JournalEntry::class,
            'reference_id' => $journal->id,
            'status' => 'posted',
        ]);

        $this->assertEquals(2, $journal->transaction()->firstOrFail()->lines()->count());
    }

    public function test_it_rejects_unbalanced_journal_entries(): void
    {
        $response = $this->from(route('journal-entries.create'))
            ->post(route('journal-entries.store'), [
                'number' => 1002,
                'date' => '2026-03-19',
                'currency_id' => $this->ctx['currency']->id,
                'rate' => 1,
                'lines' => [
                    [
                        'account_id' => $this->ctx['accounts']['cash-in-hand']->id,
                        'debit' => 500,
                        'credit' => 0,
                    ],
                    [
                        'account_id' => $this->ctx['accounts']['sales-revenue']->id,
                        'debit' => 0,
                        'credit' => 450,
                    ],
                ],
            ]);

        $response->assertRedirect(route('journal-entries.create'));
        $response->assertSessionHasErrors('lines');
    }
}
