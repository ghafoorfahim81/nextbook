<?php

namespace Tests\Feature\Http;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The sales form loads the chart of accounts a moment after it opens, as a
 * lazy shared prop. It eager-loaded a `branch` relation the Account model no
 * longer has (accounts belong to the company), so that request failed with a
 * 500 a few seconds into every sale.
 */
class SharedAccountsPropTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    public function test_the_sale_form_can_load_its_accounts(): void
    {
        $this->bootstrapErpContext();

        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $this->get(route('sales.create'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'Sale/Sales/Create',
            'X-Inertia-Partial-Data' => 'accounts',
        ])
            ->assertOk()
            ->assertJsonPath('component', 'Sale/Sales/Create')
            ->assertJsonStructure(['props' => ['accounts' => ['data']]]);
    }

    /** Every lazy list the forms pull in later, so another stale relation shows up here. */
    public function test_every_lazy_shared_list_loads(): void
    {
        $this->bootstrapErpContext();

        $version = app(\App\Http\Middleware\HandleInertiaRequests::class)->version(request());

        $this->get(route('sales.create'), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => (string) $version,
            'X-Inertia-Partial-Component' => 'Sale/Sales/Create',
            'X-Inertia-Partial-Data' => 'accounts,accountTypes,ledgers,items,roles',
        ])
            ->assertOk()
            ->assertJsonStructure(['props' => ['accounts', 'accountTypes', 'ledgers', 'items', 'roles']]);
    }
}
