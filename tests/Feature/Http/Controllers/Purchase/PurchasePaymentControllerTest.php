<?php

namespace Tests\Feature\Http\Controllers\Purchase;

use App\Models\CreatedBy;
use App\Models\PurchasePayment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Purchase\PurchasePaymentController
 */
final class PurchasePaymentControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $purchasePayments = PurchasePayment::factory()->count(3)->create();

        $response = $this->get(route('purchase-payments.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Purchase\PurchasePaymentController::class,
            'store',
            \App\Http\Requests\Purchase\PurchasePaymentStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $purchase_id = fake()->word();
        $payment_id = fake()->word();
        $amount = fake()->randomFloat(/** decimal_attributes **/);
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('purchase-payments.store'), [
            'purchase_id' => $purchase_id,
            'payment_id' => $payment_id,
            'amount' => $amount,
            'created_by' => $created_by->id,
        ]);

        $purchasePayments = PurchasePayment::query()
            ->where('purchase_id', $purchase_id)
            ->where('payment_id', $payment_id)
            ->where('amount', $amount)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $purchasePayments);
        $purchasePayment = $purchasePayments->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $purchasePayment = PurchasePayment::factory()->create();

        $response = $this->get(route('purchase-payments.show', $purchasePayment));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Purchase\PurchasePaymentController::class,
            'update',
            \App\Http\Requests\Purchase\PurchasePaymentUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $purchasePayment = PurchasePayment::factory()->create();
        $purchase_id = fake()->word();
        $payment_id = fake()->word();
        $amount = fake()->randomFloat(/** decimal_attributes **/);
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('purchase-payments.update', $purchasePayment), [
            'purchase_id' => $purchase_id,
            'payment_id' => $payment_id,
            'amount' => $amount,
            'created_by' => $created_by->id,
        ]);

        $purchasePayment->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($purchase_id, $purchasePayment->purchase_id);
        $this->assertEquals($payment_id, $purchasePayment->payment_id);
        $this->assertEquals($amount, $purchasePayment->amount);
        $this->assertEquals($created_by->id, $purchasePayment->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $purchasePayment = PurchasePayment::factory()->create();

        $response = $this->delete(route('purchase-payments.destroy', $purchasePayment));

        $response->assertNoContent();

        $this->assertModelMissing($purchasePayment);
    }
}
