<?php

namespace Tests\Feature\Attachment;

use App\Models\Attachment;
use App\Models\Purchase\Purchase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

class AttachmentTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();
    }

    private function purchasePayload(array $overrides = []): array
    {
        return array_merge([
            'number' => 7001,
            'supplier_id' => $this->ctx['supplier_ledger']->id,
            'date' => '2026-03-19',
            'transaction_total' => 300,
            'currency_id' => $this->ctx['currency']->id,
            'rate' => 1,
            'bank_account_id' => $this->ctx['accounts']['cash-in-hand']->id,
            'purchase_type' => 'cash',
            'warehouse_id' => $this->ctx['warehouse']->id,
            'description' => 'attachment test',
            'item_list' => [
                [
                    'item_id' => $this->ctx['item']->id,
                    'batch' => 'BT-100',
                    'expire_date' => '2027-03-01',
                    'quantity' => 10,
                    'unit_measure_id' => $this->ctx['unit_measure']->id,
                    'unit_price' => 30,
                    'item_discount' => 0,
                    'free' => 0,
                    'tax' => 0,
                ],
            ],
        ], $overrides);
    }

    public function test_uploaded_files_are_stored_and_linked_to_the_record(): void
    {
        Storage::fake('public');

        $response = $this->post(route('purchases.store'), $this->purchasePayload([
            'attachments' => [
                UploadedFile::fake()->create('invoice.pdf', 100, 'application/pdf'),
                UploadedFile::fake()->image('receipt.jpg'),
            ],
        ]));

        $response->assertRedirect(route('purchases.index'));

        $purchase = Purchase::query()->latest()->firstOrFail();

        $this->assertSame(2, $purchase->attachments()->count());

        $attachment = $purchase->attachments()->first();
        $this->assertSame('purchase', $attachment->attachable_type);
        $this->assertSame($purchase->id, $attachment->attachable_id);
        $this->assertTrue($attachment->attachable->is($purchase));
        Storage::disk('public')->assertExists($attachment->path);
    }

    public function test_attachment_destroy_removes_file_and_soft_deletes_row(): void
    {
        Storage::fake('public');

        $this->post(route('purchases.store'), $this->purchasePayload([
            'attachments' => [UploadedFile::fake()->create('invoice.pdf', 50, 'application/pdf')],
        ]));

        $attachment = Attachment::query()->firstOrFail();
        $path = $attachment->path;

        $this->delete(route('attachments.destroy', $attachment))->assertRedirect();

        Storage::disk('public')->assertMissing($path);
        $this->assertSoftDeleted('attachments', ['id' => $attachment->id]);
    }

    public function test_invalid_file_type_is_rejected(): void
    {
        Storage::fake('public');

        $response = $this->from(route('purchases.create'))->post(route('purchases.store'), $this->purchasePayload([
            'attachments' => [UploadedFile::fake()->create('malware.exe', 10, 'application/octet-stream')],
        ]));

        $response->assertSessionHasErrors('attachments.0');
        $this->assertSame(0, Attachment::query()->count());
    }
}
