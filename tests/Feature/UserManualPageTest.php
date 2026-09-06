<?php

use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;

uses(BuildsErpContext::class);

it('renders the in-app user manual for an authenticated user', function () {
    $this->bootstrapErpContext();

    $this->get(route('user-manual'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->component('UserManual/Index'));
});

it('does not serve the user manual as a pdf download', function () {
    $this->bootstrapErpContext();

    $response = $this->get(route('user-manual'));

    $response->assertOk();
    $response->assertHeaderMissing('content-disposition');
    expect($response->headers->get('content-type'))->not->toContain('application/pdf');
});
