<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;
use Tests\Support\BuildsErpContext;

uses(BuildsErpContext::class);

it('shows the manual prompt to a freshly onboarded user', function () {
    $this->bootstrapErpContext();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.show_manual_prompt', true));
});

it('stops showing the prompt after it is dismissed', function () {
    ['user' => $user] = $this->bootstrapErpContext();

    $this->post(route('onboarding.manual-prompt.dismiss'))->assertNoContent();

    expect(data_get($user->fresh()->preferences, 'onboarding.manual_prompt_dismissed_at'))->not->toBeNull();

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.show_manual_prompt', false));
});

it('does not resurface the prompt once a dismissed timestamp is stored', function () {
    ['user' => $user] = $this->bootstrapErpContext();

    $preferences = $user->preferences ?? User::DEFAULT_PREFERENCES;
    data_set($preferences, 'onboarding.manual_prompt_dismissed_at', now()->toIso8601String());
    $user->update(['preferences' => $preferences]);

    $this->get(route('user-manual'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page->where('auth.user.show_manual_prompt', false));
});
