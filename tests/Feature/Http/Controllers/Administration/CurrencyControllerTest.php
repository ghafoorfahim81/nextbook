<?php

namespace Tests\Feature\Http\Controllers\Administration;

use App\Models\Branch;
use App\Models\CreatedBy;
use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use JMac\Testing\Traits\AdditionalAssertions;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * @see \App\Http\Controllers\Administration\CurrencyController
 */
final class CurrencyControllerTest extends TestCase
{
    use AdditionalAssertions, RefreshDatabase, WithFaker;

    #[Test]
    public function index_behaves_as_expected(): void
    {
        $currencies = Currency::factory()->count(3)->create();

        $response = $this->get(route('currencies.index'));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function store_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\CurrencyController::class,
            'store',
            \App\Http\Requests\Administration\CurrencyStoreRequest::class
        );
    }

    #[Test]
    public function store_saves(): void
    {
        $name = $this->faker->name();
        $code = $this->faker->word();
        $symbol = $this->faker->word();
        $format = $this->faker->word();
        $exchange_rate = $this->faker->randomFloat(/** decimal_attributes **/);
        $is_active = $this->faker->boolean();
        $flag = $this->faker->word();
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->post(route('currencies.store'), [
            'name' => $name,
            'code' => $code,
            'symbol' => $symbol,
            'format' => $format,
            'exchange_rate' => $exchange_rate,
            'is_active' => $is_active,
            'flag' => $flag,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $currencies = Currency::query()
            ->where('name', $name)
            ->where('code', $code)
            ->where('symbol', $symbol)
            ->where('format', $format)
            ->where('exchange_rate', $exchange_rate)
            ->where('is_active', $is_active)
            ->where('flag', $flag)
            ->where('branch_id', $branch->id)
            ->where('created_by', $created_by->id)
            ->get();
        $this->assertCount(1, $currencies);
        $currency = $currencies->first();

        $response->assertCreated();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function show_behaves_as_expected(): void
    {
        $currency = Currency::factory()->create();

        $response = $this->get(route('currencies.show', $currency));

        $response->assertOk();
        $response->assertJsonStructure([]);
    }


    #[Test]
    public function update_uses_form_request_validation(): void
    {
        $this->assertActionUsesFormRequest(
            \App\Http\Controllers\Administration\CurrencyController::class,
            'update',
            \App\Http\Requests\Administration\CurrencyUpdateRequest::class
        );
    }

    #[Test]
    public function update_behaves_as_expected(): void
    {
        $currency = Currency::factory()->create();
        $name = $this->faker->name();
        $code = $this->faker->word();
        $symbol = $this->faker->word();
        $format = $this->faker->word();
        $exchange_rate = $this->faker->randomFloat(/** decimal_attributes **/);
        $is_active = $this->faker->boolean();
        $flag = $this->faker->word();
        $branch = Branch::factory()->create();
        $created_by = CreatedBy::factory()->create();

        $response = $this->put(route('currencies.update', $currency), [
            'name' => $name,
            'code' => $code,
            'symbol' => $symbol,
            'format' => $format,
            'exchange_rate' => $exchange_rate,
            'is_active' => $is_active,
            'flag' => $flag,
            'branch_id' => $branch->id,
            'created_by' => $created_by->id,
        ]);

        $currency->refresh();

        $response->assertOk();
        $response->assertJsonStructure([]);

        $this->assertEquals($name, $currency->name);
        $this->assertEquals($code, $currency->code);
        $this->assertEquals($symbol, $currency->symbol);
        $this->assertEquals($format, $currency->format);
        $this->assertEquals($exchange_rate, $currency->exchange_rate);
        $this->assertEquals($is_active, $currency->is_active);
        $this->assertEquals($flag, $currency->flag);
        $this->assertEquals($branch->id, $currency->branch_id);
        $this->assertEquals($created_by->id, $currency->created_by);
    }


    #[Test]
    public function destroy_deletes_and_responds_with(): void
    {
        $currency = Currency::factory()->create();

        $response = $this->delete(route('currencies.destroy', $currency));

        $response->assertNoContent();

        $this->assertModelMissing($currency);
    }
}
