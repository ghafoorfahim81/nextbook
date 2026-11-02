<?php

namespace Tests\Feature\Platform;

use App\Models\Accounting\CostCenter;
use App\Models\Platform\Package;
use App\Models\Platform\Subscription;
use App\Models\Platform\SubscriptionInvoice;
use App\Models\User;
use App\Services\SubscriptionService;
use App\Support\Modules;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * Phase 10: the platform's packages and subscriptions.
 *
 * A package decides which modules a company has and how many users and
 * branches; a subscription that ran out, or was blocked, leaves the company
 * able to read and unable to write; and only whoever runs the platform sees
 * the panel that manages it all.
 */
class SubscriptionTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        // The panel is off unless switched on; the rest of this file runs with it on.
        config(['platform.panel_enabled' => true]);
    }

    private function makePlatformAdmin(): void
    {
        $this->ctx['user']->forceFill(['is_platform_admin' => true])->save();
    }

    private function package(array $overrides = []): Package
    {
        return Package::create($overrides + [
            'code' => 'basic',
            'name' => 'Basic',
            'modules' => ['expenses'],
            'max_users' => null,
            'max_branches' => null,
            'price' => 1200,
            'currency' => 'AFN',
            'period_months' => 12,
            'is_active' => true,
        ]);
    }

    private function subscribe(Package $package, string $startsOn = '2026-01-01', ?string $endsOn = null, array $extra = []): Subscription
    {
        return Subscription::create([
            'company_id' => $this->ctx['company']->id,
            'package_id' => $package->id,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn ?? now()->addYear()->toDateString(),
        ] + $extra);
    }

    // --- Who sees the panel -------------------------------------------------

    public function test_the_panel_is_closed_to_everyone_but_the_platform_admin(): void
    {
        // This user is the company's own admin: not enough.
        $this->get(route('platform.subscriptions.index'))->assertNotFound();
        $this->get(route('platform.packages.index'))->assertNotFound();

        $this->makePlatformAdmin();

        $this->get(route('platform.subscriptions.index'))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Platform/Subscriptions/Index'));
        $this->get(route('platform.packages.index'))->assertOk();
    }

    public function test_the_panel_is_closed_when_switched_off_even_to_the_platform_admin(): void
    {
        $this->makePlatformAdmin();
        config(['platform.panel_enabled' => false]);

        $this->get(route('platform.subscriptions.index'))->assertNotFound();
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('auth.user.is_platform_admin', false));
    }

    public function test_a_company_sees_its_own_subscription_and_only_its_own(): void
    {
        $subscription = $this->subscribe($this->package(['max_users' => 5]));
        $service = app(SubscriptionService::class);
        $mine = $service->invoice($subscription, ['issued_on' => '2026-03-01', 'amount' => 100]);
        $service->recordPayment($mine->load('payments'), ['amount' => 40, 'paid_on' => '2026-03-02', 'method' => 'cash']);

        // Another company's invoice must not show up.
        $other = $this->branchInAnotherCompany();
        $otherSubscription = Subscription::create([
            'company_id' => $other->company_id,
            'package_id' => $subscription->package_id,
            'starts_on' => '2026-01-01',
            'ends_on' => now()->addYear()->toDateString(),
        ]);
        $service->invoice($otherSubscription, ['issued_on' => '2026-03-01', 'amount' => 999]);

        $this->get(route('subscription.show'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Workspace/Subscription')
                ->where('plan.package', 'Basic')
                ->where('plan.max_users', 5)
                ->where('usage.users', 1)
                ->has('invoices', 1)
                ->where('invoices.0.number', $mine->number)
                ->where('invoices.0.balance', 60));
    }

    public function test_a_company_without_a_subscription_sees_an_empty_page(): void
    {
        $this->get(route('subscription.show'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('plan', null)->has('invoices', 0));
    }

    public function test_a_blocked_company_can_still_open_its_subscription_page(): void
    {
        app(SubscriptionService::class)->block($this->subscribe($this->package()), 'Unpaid');

        $this->get(route('subscription.show'))->assertOk()
            ->assertInertia(fn ($page) => $page->where('plan.state', 'blocked')->where('plan.block_reason', 'Unpaid'));
    }

    public function test_platform_admin_is_known_to_the_pages(): void
    {
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('auth.user.is_platform_admin', false));

        $this->makePlatformAdmin();

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('auth.user.is_platform_admin', true));
    }

    // --- Packages -----------------------------------------------------------

    public function test_a_package_is_created_edited_and_kept_while_in_use(): void
    {
        $this->makePlatformAdmin();

        $this->post(route('platform.packages.store'), [
            'code' => 'pro', 'name' => 'Pro', 'modules' => ['hr', 'expenses'], 'max_users' => 5,
            'max_branches' => 2, 'price' => 5000, 'currency' => 'afn', 'period_months' => 12, 'is_active' => true,
        ])->assertSessionHasNoErrors();

        $package = Package::query()->where('code', 'pro')->firstOrFail();
        $this->assertSame('AFN', $package->currency);
        $this->assertSame(['hr', 'expenses'], $package->modules);

        // A module that does not exist is refused, and so is a duplicate code.
        $this->post(route('platform.packages.store'), [
            'code' => 'pro', 'name' => 'Again', 'modules' => ['casino'], 'price' => 1, 'currency' => 'AFN', 'period_months' => 1,
        ])->assertSessionHasErrors(['code', 'modules.0']);

        $this->put(route('platform.packages.update', $package->id), [
            'code' => 'pro', 'name' => 'Pro Plus', 'modules' => ['hr'], 'max_users' => 10,
            'price' => 6000, 'currency' => 'AFN', 'period_months' => 12, 'is_active' => true,
        ])->assertSessionHasNoErrors();
        $this->assertSame('Pro Plus', $package->fresh()->name);

        $this->subscribe($package);
        $this->delete(route('platform.packages.destroy', $package->id))->assertSessionHas('error');
        $this->assertNotSoftDeleted('packages', ['id' => $package->id]);
    }

    // --- Modules ------------------------------------------------------------

    public function test_a_module_outside_the_package_is_closed_whatever_the_settings_say(): void
    {
        // Switched on in the company's own settings…
        set_company_setting('modules.hr', true);
        set_company_setting('modules.expenses', true);

        // …and nothing limits it without a subscription.
        $this->get(route('employees.index'))->assertOk();

        $this->subscribe($this->package(['modules' => ['expenses']]));

        $this->get(route('employees.index'))->assertForbidden();
        $this->get(route('expenses.index'))->assertOk();

        // The pages are told: the sidebar loses HR and the settings tab lists only what is included.
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('modules.available', ['expenses'])
            ->where('modules.disabled_paths', fn ($paths) => collect($paths)->contains('/employees')));
    }

    public function test_a_company_without_a_subscription_is_not_limited(): void
    {
        $this->assertNull(app(SubscriptionService::class)->allowedModules($this->ctx['company']));
        $this->assertSame(Modules::available(), Modules::availableFor($this->ctx['company']));
    }

    // --- Limits -------------------------------------------------------------

    public function test_the_user_limit_stops_a_new_user(): void
    {
        $this->subscribe($this->package(['max_users' => 1]));
        $before = User::query()->count();

        // This company already has its one user.
        $this->post(route('users.store'), [
            'name' => 'Second', 'email' => 'second@example.test', 'password' => 'Secret#1234', 'password_confirmation' => 'Secret#1234',
        ])->assertSessionHas('error');

        $this->assertSame($before, User::query()->count());
    }

    public function test_the_branch_limit_stops_a_new_branch(): void
    {
        $this->subscribe($this->package(['max_branches' => 1]));
        $before = \App\Models\Administration\Branch::query()->count();

        $this->post(route('branches.store'), ['name' => 'Herat', 'location' => 'Herat'])->assertSessionHas('error');

        $this->assertSame($before, \App\Models\Administration\Branch::query()->count());
    }

    // --- Read-only ----------------------------------------------------------

    public function test_an_expired_company_can_read_but_not_write(): void
    {
        $this->subscribe($this->package(), '2025-01-01', now()->subDays(3)->toDateString());

        $this->get(route('cost-centers.index'))->assertOk();

        $this->post(route('cost-centers.store'), ['name' => 'Head office'])->assertSessionHas('error');
        $this->assertSame(0, CostCenter::query()->count());

        // A JSON caller gets a 403 with the same message.
        $this->postJson(route('cost-centers.store'), ['name' => 'Head office'])->assertForbidden();

        // What is not about the books still goes through.
        $this->post(route('locale.update'), ['locale' => 'en'])->assertSessionDoesntHaveErrors();
    }

    public function test_a_blocked_company_is_read_only_until_unblocked(): void
    {
        $subscription = $this->subscribe($this->package());
        app(SubscriptionService::class)->block($subscription, 'Unpaid invoice');

        $this->post(route('cost-centers.store'), ['name' => 'Head office'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Unpaid invoice'));
        $this->assertSame(0, CostCenter::query()->count());

        app(SubscriptionService::class)->unblock($subscription);

        $this->post(route('cost-centers.store'), ['name' => 'Head office'])->assertSessionHasNoErrors();
        $this->assertSame(1, CostCenter::query()->count());
    }

    public function test_the_platform_admin_is_not_stopped_by_a_blocked_company(): void
    {
        app(SubscriptionService::class)->block($this->subscribe($this->package()), 'Unpaid');
        $this->makePlatformAdmin();

        $this->post(route('cost-centers.store'), ['name' => 'Head office'])->assertSessionHasNoErrors();
        $this->assertSame(1, CostCenter::query()->count());
    }

    public function test_the_banner_says_when_a_subscription_needs_attention(): void
    {
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('subscription', null));

        $subscription = $this->subscribe($this->package(), '2026-01-01', now()->addDays(5)->toDateString());
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('subscription.state', 'expiring')->where('subscription.days_left', 5));

        $subscription->update(['ends_on' => now()->subDay()->toDateString()]);
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('subscription.state', 'expired'));

        $subscription->update(['ends_on' => now()->addYear()->toDateString()]);
        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page->where('subscription', null));
    }

    // --- Renewing, invoicing, paying ----------------------------------------

    public function test_renewing_counts_from_the_end_or_from_today_when_it_already_ended(): void
    {
        $service = app(SubscriptionService::class);

        // Paid early: nothing is lost.
        $early = $this->subscribe($this->package(), '2026-01-01', now()->addDays(10)->toDateString());
        $service->renew($early);
        $this->assertSame(now()->addDays(10)->addYear()->toDateString(), $early->fresh()->ends_on->toDateString());

        // Paid late: the days in between are not given back.
        $early->update(['ends_on' => now()->subDays(40)->toDateString()]);
        $service->renew($early->fresh(), 6);
        $this->assertSame(now()->startOfDay()->addMonthsNoOverflow(6)->toDateString(), $early->fresh()->ends_on->toDateString());
    }

    public function test_renewing_can_invoice_the_new_period_pro_rata(): void
    {
        $this->makePlatformAdmin();
        $this->subscribe($this->package(['price' => 1200, 'period_months' => 12]), '2026-01-01', now()->addDays(3)->toDateString());

        $this->post(route('platform.subscriptions.renew', $this->ctx['company']->id), ['months' => 6, 'invoice' => true])
            ->assertSessionHasNoErrors();

        $invoice = SubscriptionInvoice::query()->firstOrFail();
        $this->assertSame(600.0, (float) $invoice->amount);
        $this->assertMatchesRegularExpression('/^SUB-\d{4}-0001$/', $invoice->number);
        $this->assertSame($this->ctx['company']->id, $invoice->company_id);
    }

    public function test_payments_settle_an_invoice_and_cannot_exceed_it(): void
    {
        $this->makePlatformAdmin();
        $subscription = $this->subscribe($this->package());
        $company = $this->ctx['company']->id;

        $this->post(route('platform.subscriptions.invoices.store', $company), [
            'issued_on' => '2026-03-01', 'amount' => 1000, 'currency' => 'AFN', 'description' => 'Year 1',
        ])->assertSessionHasNoErrors();
        $invoice = SubscriptionInvoice::query()->firstOrFail();

        $pay = fn (float $amount) => $this->post(route('platform.subscriptions.payments.store', [$company, $invoice->id]), [
            'amount' => $amount, 'paid_on' => '2026-03-05', 'method' => 'hawala', 'reference' => 'HW-1',
        ]);

        $pay(400)->assertSessionHasNoErrors();
        $this->assertSame('partial', $invoice->fresh('payments')->paymentState());
        $this->assertSame(600.0, (float) $invoice->fresh('payments')->balance());

        // More than what is left is refused.
        $pay(700)->assertSessionHas('error');
        $this->assertCount(1, $invoice->fresh('payments')->payments);

        $pay(600)->assertSessionHasNoErrors();
        $this->assertSame('paid', $invoice->fresh('payments')->paymentState());

        // A void invoice takes no payment.
        $other = app(SubscriptionService::class)->invoice($subscription, ['issued_on' => '2026-04-01', 'amount' => 50]);
        $this->post(route('platform.subscriptions.invoices.void', [$company, $other->id]))->assertSessionHasNoErrors();
        $this->post(route('platform.subscriptions.payments.store', [$company, $other->id]), [
            'amount' => 10, 'paid_on' => '2026-04-02', 'method' => 'cash',
        ])->assertSessionHas('error');
    }

    public function test_invoice_numbers_run_on_across_companies(): void
    {
        $service = app(SubscriptionService::class);
        $subscription = $this->subscribe($this->package());

        $first = $service->invoice($subscription, ['issued_on' => '2026-03-01', 'amount' => 10]);
        $second = $service->invoice($subscription, ['issued_on' => '2026-03-02', 'amount' => 10]);

        $this->assertSame('SUB-2026-0001', $first->number);
        $this->assertSame('SUB-2026-0002', $second->number);
    }

    public function test_assigning_a_package_sets_the_dates_and_refuses_an_end_before_the_start(): void
    {
        $this->makePlatformAdmin();
        $package = $this->package();
        $company = $this->ctx['company']->id;

        $this->put(route('platform.subscriptions.update', $company), [
            'package_id' => $package->id, 'starts_on' => '2026-06-01', 'ends_on' => '2026-01-01',
        ])->assertSessionHasErrors('ends_on');

        $this->put(route('platform.subscriptions.update', $company), [
            'package_id' => $package->id, 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'notes' => 'Sold at the Kabul fair',
        ])->assertSessionHasNoErrors();

        $subscription = Subscription::query()->where('company_id', $company)->firstOrFail();
        $this->assertSame('2026-12-31', $subscription->ends_on->toDateString());

        $this->get(route('platform.subscriptions.show', $company))->assertOk()
            ->assertInertia(fn ($page) => $page->component('Platform/Subscriptions/Show')->where('plan.package', 'Basic'));
    }

    public function test_the_customer_page_does_not_shadow_the_shared_banner(): void
    {
        $this->makePlatformAdmin();
        $this->subscribe($this->package(), '2026-01-01', now()->addDays(5)->toDateString());

        // The page's own data is `plan`; `subscription` stays the banner's.
        $this->get(route('platform.subscriptions.show', $this->ctx['company']->id))
            ->assertInertia(fn ($page) => $page
                ->where('plan.state', 'expiring')
                ->has('subscription.ends_on_display')
                ->where('subscription.days_left', 5));
    }

    public function test_the_list_shows_every_company_with_its_usage(): void
    {
        $this->makePlatformAdmin();
        $this->subscribe($this->package(['max_users' => 5]));

        $this->get(route('platform.subscriptions.index'))->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('totals.subscribed', 1)
                ->where('companies.data.0.package', 'Basic')
                ->where('companies.data.0.max_users', 5)
                ->where('companies.data.0.users', 1));
    }
}
