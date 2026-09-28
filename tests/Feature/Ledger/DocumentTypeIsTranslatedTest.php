<?php

namespace Tests\Feature\Ledger;

use App\Enums\StockMovementType;
use App\Enums\StockSourceType;
use App\Enums\StockStatus;
use App\Models\Ledger\Ledger;
use App\Services\StockService;
use App\Support\DocumentType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The "document type" column, in the reader's language.
 *
 * Every screen used to run its own `class_basename()` on `reference_type` and
 * print the result, so a Persian statement said "Contra Settlement" and a
 * Persian report said "Sale". The naming now happens in one place, server
 * side, which also covers the Excel and PDF exports — they never see a Vue
 * component.
 */
class DocumentTypeIsTranslatedTest extends TestCase
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

    public function test_it_names_documents_in_the_active_language(): void
    {
        app()->setLocale('en');
        $this->assertSame('Sale', DocumentType::label(\App\Models\Sale\Sale::class));
        $this->assertSame('Set-off', DocumentType::label(\App\Models\Accounting\ContraSettlement::class));

        app()->setLocale('fa');
        $this->assertSame('فروش', DocumentType::label(\App\Models\Sale\Sale::class));
        $this->assertSame('تهاتر', DocumentType::label(\App\Models\Accounting\ContraSettlement::class));
        $this->assertSame('برگشت خرید', DocumentType::label(\App\Models\Purchase\PurchaseReturn::class));

        app()->setLocale('ps');
        $this->assertSame('خرڅلاو', DocumentType::label(\App\Models\Sale\Sale::class));
    }

    public function test_the_bare_slugs_and_the_missing_reference_are_named_too(): void
    {
        app()->setLocale('fa');

        // 'reversal' is stored as a bare slug, not a class name.
        $this->assertSame('ابطال', DocumentType::label('reversal'));
        // A transaction with no reference at all is a manual journal.
        $this->assertSame('سند حسابداری', DocumentType::label(null));
        // Openings are identified by their row in ledger_openings, not by type.
        $this->assertSame('بیلانس اولیه', DocumentType::label(null, isOpening: true));
    }

    /**
     * A document nobody has translated yet must read as English, not as a raw
     * key. Getting this wrong is how `document_type.something` ends up on a
     * customer's statement.
     */
    public function test_an_untranslated_type_falls_back_to_readable_english(): void
    {
        app()->setLocale('fa');

        $this->assertSame('Warranty Claim', DocumentType::label('App\\Models\\Support\\WarrantyClaim'));
    }

    /**
     * The real path: a Persian-speaking user opens a party page.
     *
     * The locale is not a global the test can set and forget — SetLocale
     * reads it off `users.locale` on every request, so proving this works
     * means going through HTTP as a user whose language is Persian.
     */
    public function test_the_statement_carries_the_translated_name(): void
    {
        $this->ctx['user']->forceFill(['locale' => 'fa'])->saveQuietly();

        $customer = $this->ctx['customer_ledger'];

        app(StockService::class)->post([
            'item_id' => $this->ctx['item']->id,
            'movement_type' => StockMovementType::IN->value,
            'unit_measure_id' => $this->ctx['unit_measure']->id,
            'quantity' => 100,
            'source' => StockSourceType::OPENING->value,
            'unit_cost' => 10,
            'status' => StockStatus::POSTED->value,
            'batch' => null,
            'expire_date' => null,
            'date' => now()->toDateString(),
            'warehouse_id' => $this->ctx['warehouse']->id,
            'branch_id' => $this->ctx['branch']->id,
            'reference_type' => null,
            'reference_id' => null,
        ]);

        $this->post(route('sales.store'), [
            'number' => 4001,
            'customer_id' => $customer->id,
            'date' => now()->toDateString(),
            'transaction_total' => 1000,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'sale_type' => 'on_loan',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'item_list' => [[
                'item_id' => $this->ctx['item']->id,
                'batch' => null, 'expire_date' => null,
                'quantity' => 50,
                'unit_measure_id' => $this->ctx['unit_measure']->id,
                'unit_price' => 20,
                'item_discount' => 0, 'free' => 0, 'tax' => 0,
            ]],
        ])->assertRedirect(route('sales.index'))->assertSessionHasNoErrors();

        $response = $this->getJson(route('customers.show', $customer->id));
        $response->assertOk();

        $types = collect($response->json('ledgerStatement.sections'))
            ->flatMap(fn ($section) => collect($section['rows'] ?? [])->pluck('document_type'))
            ->unique()
            ->values()
            ->all();

        $this->assertContains('فروش', $types, 'The statement should name the sale in Persian; saw: '.implode(', ', $types));
        $this->assertNotContains('Sale', $types);
    }
}
