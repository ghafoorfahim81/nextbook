<?php

namespace Tests\Feature\Accounting;

use App\Models\Payment\Payment;
use App\Models\Receipt\Receipt;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The next number offered on a receipt or payment form.
 *
 * `receipts.number` and `payments.number` are STRING columns, so MAX() sorts
 * them as text: '9' is greater than '10'. With ten receipts on file the maximum
 * was '9' and the form offered 10 — the number that already existed — for every
 * receipt from then on. The counter stuck there permanently.
 *
 * sales.number and purchases.number are integer columns and were never affected.
 */
class VoucherNumberingTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function receipt(string $number): Receipt
    {
        return Receipt::factory()->create([
            'number' => $number,
            'branch_id' => $this->ctx['branch']->id,
            'ledger_id' => $this->ctx['customer_ledger']->id,
            'date' => '2026-03-01',
        ]);
    }

    public function test_the_first_voucher_is_number_one(): void
    {
        $this->assertSame(1, Receipt::nextNumber());
        $this->assertSame(1, Payment::nextNumber());
    }

    public function test_it_counts_past_nine_instead_of_sticking_on_ten(): void
    {
        foreach (range(1, 9) as $number) {
            $this->receipt((string) $number);
        }

        // Text MAX of '1'..'9' is '9', which is also the numeric maximum, so
        // the old code was right up to here.
        $this->assertSame(10, Receipt::nextNumber());

        $this->receipt('10');

        // And here it broke: text MAX of '1'..'10' is still '9', so it offered
        // 10 again — for ever.
        $this->assertSame(11, Receipt::nextNumber());

        $this->receipt('11');
        $this->assertSame(12, Receipt::nextNumber());
    }

    public function test_it_keeps_counting_across_the_next_boundaries(): void
    {
        $this->receipt('99');
        $this->assertSame(100, Receipt::nextNumber());

        $this->receipt('100');
        $this->assertSame(101, Receipt::nextNumber());

        $this->receipt('999');
        $this->assertSame(1000, Receipt::nextNumber());
    }

    public function test_a_soft_deleted_voucher_keeps_its_number(): void
    {
        $this->receipt('1');
        $this->receipt('2')->delete();

        // Reissuing 2 would collide with the deleted receipt the moment anyone
        // restored it from the deleted-records screen.
        $this->assertSame(3, Receipt::nextNumber());
    }

    public function test_a_non_numeric_number_does_not_break_the_sequence(): void
    {
        $this->receipt('7');
        $this->receipt('INV-2024/3');

        // Ignored rather than crashing the cast — and 7 still counts.
        $this->assertSame(8, Receipt::nextNumber());
    }

    public function test_the_create_page_offers_the_next_number(): void
    {
        foreach (range(1, 10) as $number) {
            $this->receipt((string) $number);
        }

        $this->get(route('receipts.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('latestNumber', 11));
    }

    public function test_numbering_is_per_branch(): void
    {
        $this->receipt('10');

        $other = \App\Models\Administration\Branch::factory()->create([
            'name' => 'Second Branch',
            'is_main' => false,
            'created_by' => $this->ctx['user']->id,
        ]);

        app()->instance('active_branch_id', $other->id);

        // Each branch runs its own sequence, as it did before.
        $this->assertSame(1, Receipt::nextNumber());
    }
}
