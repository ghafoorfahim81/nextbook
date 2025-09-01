<?php

namespace Tests\Feature\Http\Controllers\Purchase;

use App\Models\CreatedBy;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Purchase\PurchaseController
 */
final class PurchaseControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $purchases = Purchase::factory()->count(3)->create();

        $response = $this->get(route('purchases.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Purchase\PurchaseController::class,
            'store',
            \App\Http\Requests\Purchase\PurchaseStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $number = fake()->word();
        $supplier = Supplier::factory()->create();
        $date = Carbon::parse(fake()->date());
        $transaction = Transaction::factory()->create();
        $type = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('purchases.store'), [
            'number' => $number,
            'supplier_id' => $supplier->id,
            'date' => $date->toDateString(),
            'transaction_id' => $transaction->id,
            'type' => $type,
            'created_by' => $created_by->id,
        ]);

        $purchases = Purchase::query()
            ->where('number', $number)
            ->where('supplier_id', $supplier->id)
            ->where('date', $date)
            ->where('transaction_id', $transaction->id)
            ->where('type', $type)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $purchases);
        $purchase = $purchases->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $purchase = Purchase::factory()->create();

        $response = $this->get(route('purchases.show', $purchase));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Purchase\PurchaseController::class,
            'update',
            \App\Http\Requests\Purchase\PurchaseUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $purchase = Purchase::factory()->create();
        $number = fake()->word();
        $supplier = Supplier::factory()->create();
        $date = Carbon::parse(fake()->date());
        $transaction = Transaction::factory()->create();
        $type = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('purchases.update', $purchase), [
            'number' => $number,
            'supplier_id' => $supplier->id,
            'date' => $date->toDateString(),
            'transaction_id' => $transaction->id,
            'type' => $type,
            'created_by' => $created_by->id,
        ]);

        $purchase->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($number, $purchase->number);
        $this->assertEquals($supplier->id, $purchase->supplier_id);
        $this->assertEquals($date, $purchase->date);
        $this->assertEquals($transaction->id, $purchase->transaction_id);
        $this->assertEquals($type, $purchase->type);
        $this->assertEquals($created_by->id, $purchase->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $purchase = Purchase::factory()->create();

        $response = $this->delete(route('purchases.destroy', $purchase));

        $response->assertNoContent();

        $this->assertModelMissing($purchase);
    }
}
