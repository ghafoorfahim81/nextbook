<?php

namespace Tests\Feature\Http\Controllers\Transaction;

use App\Models\CreatedBy;
use App\Models\Currency;
use App\Models\Transaction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Carbon;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Transaction\TransactionController
 */
final class TransactionControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $transactions = Transaction::factory()->count(3)->create();

        $response = $this->get(route('transactions.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Transaction\TransactionController::class,
            'store',
            \App\Http\Requests\Transaction\TransactionStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $transactionable = fake()->word();
        $amount = fake()->randomFloat(/** float_attributes **/);
        $currency = Currency::factory()->create();
        $rate = fake()->randomFloat(/** float_attributes **/);
        $date = Carbon::parse(fake()->date());
        $type = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('transactions.store'), [
            'transactionable' => $transactionable,
            'amount' => $amount,
            'currency_id' => $currency->id,
            'rate' => $rate,
            'date' => $date->toDateString(),
            'type' => $type,
            'created_by' => $created_by->id,
        ]);

        $transactions = Transaction::query()
            ->where('transactionable', $transactionable)
            ->where('amount', $amount)
            ->where('currency_id', $currency->id)
            ->where('rate', $rate)
            ->where('date', $date)
            ->where('type', $type)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $transactions);
        $transaction = $transactions->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $transaction = Transaction::factory()->create();

        $response = $this->get(route('transactions.show', $transaction));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Transaction\TransactionController::class,
            'update',
            \App\Http\Requests\Transaction\TransactionUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $transaction = Transaction::factory()->create();
        $transactionable = fake()->word();
        $amount = fake()->randomFloat(/** float_attributes **/);
        $currency = Currency::factory()->create();
        $rate = fake()->randomFloat(/** float_attributes **/);
        $date = Carbon::parse(fake()->date());
        $type = fake()->word();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('transactions.update', $transaction), [
            'transactionable' => $transactionable,
            'amount' => $amount,
            'currency_id' => $currency->id,
            'rate' => $rate,
            'date' => $date->toDateString(),
            'type' => $type,
            'created_by' => $created_by->id,
        ]);

        $transaction->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($transactionable, $transaction->transactionable);
        $this->assertEquals($amount, $transaction->amount);
        $this->assertEquals($currency->id, $transaction->currency_id);
        $this->assertEquals($rate, $transaction->rate);
        $this->assertEquals($date, $transaction->date);
        $this->assertEquals($type, $transaction->type);
        $this->assertEquals($created_by->id, $transaction->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $transaction = Transaction::factory()->create();

        $response = $this->delete(route('transactions.destroy', $transaction));

        $response->assertNoContent();

        $this->assertModelMissing($transaction);
    }
}
