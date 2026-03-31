<?php

use App\Models\Administration\Currency;
use App\Models\Administration\CurrencyRateUpdate;
use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;

uses(BuildsErpContext::class);

it('renders the currency rate update page with foreign currencies and history', function () {
    $this->actingAs(User::factory()->create());
    $ctx = $this->bootstrapErpContext();

    $usd = Currency::factory()->create([
        'branch_id' => $ctx['branch']->id,
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'format' => '$1,0.00',
        'exchange_rate' => 74.50,
        'is_active' => true,
        'is_base_currency' => false,
    ]);

    CurrencyRateUpdate::create([
        'currency_id' => $usd->id,
        'exchange_rate' => 74.50,
        'date' => '2026-03-31',
    ]);

    $response = $this->get(route('currency-rate-updates.index'));

    $response->assertOk();
    $response->assertInertia(function (AssertableInertia $page) {
        $page->component('Administration/CurrencyRateUpdates/Index')
            ->where('homeCurrency.data.code', 'AFN')
            ->has('currencies.data', 1)
            ->where('currencies.data.0.code', 'USD')
            ->has('history.data', 1)
            ->where('history.data.0.currency.code', 'USD');
    });
});

it('updates foreign currency rates and stores history rows', function () {
    $this->actingAs(User::factory()->create());
    $ctx = $this->bootstrapErpContext();

    $usd = Currency::factory()->create([
        'branch_id' => $ctx['branch']->id,
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'format' => '$1,0.00',
        'exchange_rate' => 74.50,
        'is_active' => true,
        'is_base_currency' => false,
    ]);

    $eur = Currency::factory()->create([
        'branch_id' => $ctx['branch']->id,
        'name' => 'Euro',
        'code' => 'EUR',
        'symbol' => '€',
        'format' => '€1,0.00',
        'exchange_rate' => 82.10,
        'is_active' => true,
        'is_base_currency' => false,
    ]);

    $response = $this->post(route('currency-rate-updates.store'), [
        'date' => '2026-03-31',
        'updates' => [
            [
                'currency_id' => $usd->id,
                'exchange_rate' => 75.25,
            ],
            [
                'currency_id' => $eur->id,
                'exchange_rate' => 83.75,
            ],
        ],
    ]);

    $response->assertRedirect(route('currency-rate-updates.index'));

    expect((float) $usd->fresh()->exchange_rate)->toBe(75.25);
    expect((float) $eur->fresh()->exchange_rate)->toBe(83.75);

    $this->assertDatabaseHas('currency_rate_updates', [
        'currency_id' => $usd->id,
        'exchange_rate' => 75.25,
        'date' => '2026-03-31',
        'branch_id' => $ctx['branch']->id,
    ]);

    $this->assertDatabaseHas('currency_rate_updates', [
        'currency_id' => $eur->id,
        'exchange_rate' => 83.75,
        'date' => '2026-03-31',
        'branch_id' => $ctx['branch']->id,
    ]);
});

it('ignores unchanged currency rates when saving updates', function () {
    $this->actingAs(User::factory()->create());
    $ctx = $this->bootstrapErpContext();

    $usd = Currency::factory()->create([
        'branch_id' => $ctx['branch']->id,
        'name' => 'US Dollar',
        'code' => 'USD',
        'symbol' => '$',
        'format' => '$1,0.00',
        'exchange_rate' => 0.01000000,
        'is_active' => true,
        'is_base_currency' => false,
    ]);

    $irr = Currency::factory()->create([
        'branch_id' => $ctx['branch']->id,
        'name' => 'Iranian Rial',
        'code' => 'IRR',
        'symbol' => 'Rial',
        'format' => '1,0/00 Rial',
        'exchange_rate' => 2500.00000000,
        'is_active' => true,
        'is_base_currency' => false,
    ]);

    $response = $this->post(route('currency-rate-updates.store'), [
        'date' => '2026-03-31',
        'updates' => [
            [
                'currency_id' => $usd->id,
                'exchange_rate' => 0.0245,
            ],
            [
                'currency_id' => $irr->id,
                'exchange_rate' => 2500.00000000,
            ],
        ],
    ]);

    $response->assertRedirect(route('currency-rate-updates.index'));

    expect((float) $usd->fresh()->exchange_rate)->toBe(0.0245);
    expect((float) $irr->fresh()->exchange_rate)->toBe(2500.0);

    $this->assertDatabaseHas('currency_rate_updates', [
        'currency_id' => $usd->id,
        'exchange_rate' => 0.0245,
        'date' => '2026-03-31',
        'branch_id' => $ctx['branch']->id,
    ]);

    $this->assertDatabaseMissing('currency_rate_updates', [
        'currency_id' => $irr->id,
        'date' => '2026-03-31',
        'branch_id' => $ctx['branch']->id,
    ]);

    expect(CurrencyRateUpdate::query()->count())->toBe(1);
});
