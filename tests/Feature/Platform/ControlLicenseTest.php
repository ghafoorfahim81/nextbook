<?php

namespace Tests\Feature\Platform;

use App\Models\Accounting\CostCenter;
use App\Models\Platform\CompanyLicense;
use App\Models\Platform\Package;
use App\Models\Platform\Subscription;
use App\Models\User;
use App\Services\Control\LicenseClient;
use App\Services\Control\LicenseVerifier;
use App\Services\SubscriptionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The control center's licence reaching the ERP (phase 10): fetched, verified,
 * remembered, and then enforced by the same read-only, module and limit rules
 * that were already there.
 */
class ControlLicenseTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private array $ctx;

    private string $secretKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ctx = $this->bootstrapErpContext();

        $pair = sodium_crypto_sign_keypair();
        $this->secretKey = sodium_crypto_sign_secretkey($pair);
        config([
            'control.url' => 'https://control.test',
            'control.public_key' => base64_encode(sodium_crypto_sign_publickey($pair)),
            'control.stale_after_hours' => 36,
        ]);
    }

    private function companyId(): string
    {
        return $this->ctx['company']->id;
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'v' => 1,
            'customer_id' => '01M6DTM02SCYWQ21QAJD5NYKH0',
            'erp_company_id' => $this->companyId(),
            'license_version' => 1,
            'issued_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'),
            'customer_status' => 'active',
            'state' => 'active',
            'kind' => 'paid',
            'package' => 'Starter',
            'starts_on' => now()->subMonth()->toDateString(),
            'ends_on' => now()->addYear()->toDateString(),
            'blocked' => false,
            'writes_allowed' => true,
            'block_reason' => null,
            'modules' => ['expenses'],
            'max_users' => null,
            'max_branches' => null,
            'max_storage_mb' => null,
            'max_monthly_documents' => null,
        ];
    }

    private function sign(array $payload): string
    {
        return base64_encode(sodium_crypto_sign_detached(LicenseVerifier::canonical($payload), $this->secretKey));
    }

    /** Stores a licence the way a successful pull does. */
    private function hold(array $payload = [], array $row = []): CompanyLicense
    {
        $payload = $this->payload($payload);

        return CompanyLicense::query()->updateOrCreate(['company_id' => $this->companyId()], $row + [
            'api_token' => 'tok-123',
            'control_customer_id' => $payload['customer_id'],
            'payload' => $payload,
            'signature' => $this->sign($payload),
            'license_version' => $payload['license_version'],
            'grace_days' => 7,
            'fetched_at' => now(),
        ]);
    }

    private function connect(): CompanyLicense
    {
        return CompanyLicense::query()->create(['company_id' => $this->companyId(), 'api_token' => 'tok-123']);
    }

    /** A second Http::fake() would not replace the first (the first match wins), so start clean. */
    private function fakeHttp(array $stubs = []): void
    {
        Http::swap(new Factory());
        Http::fake($stubs ?: null);
    }

    private function fakeControl(array $payload, ?string $signature = null, int $status = 200): void
    {
        $this->fakeHttp(['control.test/api/license' => Http::response(['payload' => $payload, 'signature' => $signature ?? $this->sign($payload), 'grace_days' => 5], $status)]);
    }

    // --- Compatibility with the control center ----------------------------

    public function test_the_golden_vector_made_by_the_control_center_verifies_here(): void
    {
        $vector = json_decode(file_get_contents(base_path('tests/Fixtures/licence-golden.json')), true);

        $this->assertSame($vector['canonical'], LicenseVerifier::canonical($vector['payload']));
        $this->assertTrue(LicenseVerifier::verify($vector['payload'], $vector['signature'], $vector['public_key']));

        $forged = $vector['payload'];
        $forged['max_users'] = 999;
        $this->assertFalse(LicenseVerifier::verify($forged, $vector['signature'], $vector['public_key']));
    }

    public function test_nothing_verifies_without_a_public_key_or_with_garbage(): void
    {
        $payload = $this->payload();
        $signature = $this->sign($payload);

        $this->assertFalse(LicenseVerifier::verify($payload, $signature, null));
        $this->assertFalse(LicenseVerifier::verify($payload, 'not base64!!', config('control.public_key')));
        $this->assertFalse(LicenseVerifier::verify($payload, base64_encode('short'), config('control.public_key')));
    }

    // --- Connecting and pulling -------------------------------------------

    public function test_connecting_stores_the_token_encrypted(): void
    {
        Artisan::call('license:connect', ['company' => $this->companyId(), 'token' => 'secret-token-value']);

        $raw = DB::table('company_licenses')->where('company_id', $this->companyId())->value('api_token');
        $this->assertNotSame('secret-token-value', $raw);
        $this->assertSame('secret-token-value', CompanyLicense::query()->firstOrFail()->api_token);
        $this->assertArrayNotHasKey('api_token', CompanyLicense::query()->firstOrFail()->toArray());
    }

    public function test_connecting_an_unknown_company_fails(): void
    {
        $this->assertSame(1, Artisan::call('license:connect', ['company' => '01ZZZZZZZZZZZZZZZZZZZZZZZZ', 'token' => 'x']));
    }

    public function test_a_valid_licence_is_fetched_verified_and_kept(): void
    {
        $this->connect();
        $payload = $this->payload(['license_version' => 4]);
        $this->fakeControl($payload);

        $result = app(LicenseClient::class)->pull($this->companyId());

        $this->assertTrue($result['ok'], $result['message']);
        $row = CompanyLicense::query()->firstOrFail();
        $this->assertSame(4, $row->license_version);
        $this->assertSame(5, $row->grace_days);
        $this->assertSame($payload['customer_id'], $row->control_customer_id);
        $this->assertNotNull($row->fetched_at);
        $this->assertNull($row->last_error);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok-123') && $request->url() === 'https://control.test/api/license');
    }

    public function test_a_licence_with_a_bad_signature_is_refused_and_the_old_one_stays(): void
    {
        $this->hold(['max_users' => 3, 'license_version' => 2]);
        $this->fakeControl($this->payload(['license_version' => 3, 'max_users' => 999]), base64_encode(str_repeat('a', 64)));

        $result = app(LicenseClient::class)->pull($this->companyId());

        $this->assertFalse($result['ok']);
        $row = CompanyLicense::query()->firstOrFail();
        $this->assertSame(2, $row->license_version);
        $this->assertSame(3, $row->payload['max_users']);
        $this->assertStringContainsString('signature', $row->last_error);
    }

    public function test_a_licence_for_another_company_or_customer_is_refused(): void
    {
        $this->hold();

        $this->fakeControl($this->payload(['license_version' => 2, 'erp_company_id' => '01ZZZZZZZZZZZZZZZZZZZZZZZZ']));
        $this->assertStringContainsString('another company', app(LicenseClient::class)->pull($this->companyId())['message']);

        $this->fakeControl($this->payload(['license_version' => 2, 'customer_id' => '01M6DTM02SCYWQ21QAJD5NYKH9']));
        $this->assertStringContainsString('another customer', app(LicenseClient::class)->pull($this->companyId())['message']);

        $this->assertSame(1, CompanyLicense::query()->firstOrFail()->license_version);
    }

    public function test_an_older_licence_cannot_undo_a_newer_one_but_the_same_version_refreshes_it(): void
    {
        $this->hold(['license_version' => 5, 'blocked' => true, 'block_reason' => 'Unpaid'], ['fetched_at' => now()->subDay()]);

        // A late reply from before the block was lifted would otherwise bring it back.
        $this->fakeControl($this->payload(['license_version' => 4, 'blocked' => false]));
        $this->assertFalse(app(LicenseClient::class)->pull($this->companyId())['ok']);
        $this->assertTrue(CompanyLicense::query()->firstOrFail()->payload['blocked']);

        $this->fakeControl($this->payload(['license_version' => 5, 'blocked' => true, 'block_reason' => 'Unpaid']));
        $this->assertTrue(app(LicenseClient::class)->pull($this->companyId())['ok']);
        $this->assertTrue(CompanyLicense::query()->firstOrFail()->fetched_at->isToday());
    }

    public function test_an_unreachable_control_center_changes_nothing_and_says_why(): void
    {
        $this->hold(['license_version' => 2]);
        $this->fakeHttp(['control.test/*' => fn () => throw new ConnectionException('down')]);

        $result = app(LicenseClient::class)->pull($this->companyId());

        $this->assertFalse($result['ok']);
        $row = CompanyLicense::query()->firstOrFail();
        $this->assertSame(2, $row->license_version);
        $this->assertSame('The control center could not be reached.', $row->last_error);
    }

    public function test_an_error_reply_and_a_reply_that_is_not_a_licence_are_refused(): void
    {
        $this->hold();

        $this->fakeHttp(['control.test/*' => Http::response(['message' => 'Unauthenticated.'], 401)]);
        $this->assertStringContainsString('401', app(LicenseClient::class)->pull($this->companyId())['message']);

        $this->fakeHttp(['control.test/*' => Http::response(['hello' => 'world'], 200)]);
        $this->assertSame('The reply was not a licence.', app(LicenseClient::class)->pull($this->companyId())['message']);
    }

    public function test_without_a_configured_public_key_no_licence_is_accepted(): void
    {
        $this->connect();
        config(['control.public_key' => null]);
        $this->fakeControl($this->payload());

        $this->assertFalse(app(LicenseClient::class)->pull($this->companyId())['ok']);
        $this->assertNull(CompanyLicense::query()->firstOrFail()->payload);
    }

    public function test_the_pull_command_goes_through_the_connected_companies_only(): void
    {
        $this->connect();
        $this->fakeControl($this->payload());

        $this->assertSame(0, Artisan::call('license:pull'));
        $this->assertNotNull(CompanyLicense::query()->firstOrFail()->payload);
        Http::assertSentCount(2); // the licence, then the invoices; only for the one connected company
    }

    // --- Enforcement from the licence -------------------------------------

    public function test_the_licence_stands_in_for_a_subscription_everywhere_it_is_read(): void
    {
        $this->hold(['modules' => ['expenses'], 'max_users' => 5, 'max_branches' => 2, 'package' => 'Starter']);

        $subscription = app(SubscriptionService::class)->forCompany($this->companyId());

        $this->assertSame('Starter', $subscription->package->name);
        $this->assertSame(['expenses'], $subscription->package->modules);
        $this->assertSame(5, $subscription->package->max_users);
        $this->assertSame('active', $subscription->state());
        $this->assertSame(['expenses'], app(SubscriptionService::class)->allowedModules($this->companyId()));
    }

    public function test_a_module_outside_the_licence_is_closed(): void
    {
        set_company_setting('modules.hr', true);
        set_company_setting('modules.expenses', true);
        $this->hold(['modules' => ['expenses']]);

        $this->get(route('employees.index'))->assertForbidden();
        $this->get(route('expenses.index'))->assertOk();
    }

    public function test_a_blocked_licence_leaves_the_company_able_to_read_but_not_write(): void
    {
        $this->hold(['blocked' => true, 'block_reason' => 'Unpaid invoice']);

        $this->get(route('cost-centers.index'))->assertOk();
        $this->post(route('cost-centers.store'), ['name' => 'Head office'])
            ->assertSessionHas('error', fn ($message) => str_contains($message, 'Unpaid invoice'));
        $this->assertSame(0, CostCenter::query()->count());
    }

    public function test_the_end_date_is_judged_each_day_not_when_the_licence_was_issued(): void
    {
        // The licence says "active", but its end date has passed since.
        $this->hold(['state' => 'active', 'writes_allowed' => true, 'ends_on' => now()->subDays(2)->toDateString()], ['fetched_at' => now()]);

        $this->assertSame('expired', app(SubscriptionService::class)->forCompany($this->companyId())->state());
        $this->post(route('cost-centers.store'), ['name' => 'Head office'])->assertSessionHas('error');
    }

    public function test_when_the_control_center_has_been_out_of_reach_the_end_is_stretched_by_the_grace_days_and_no_further(): void
    {
        // Last heard from three days ago (stale), ended two days ago, seven days of grace.
        $this->hold(['ends_on' => now()->subDays(2)->toDateString()], ['fetched_at' => now()->subDays(3), 'grace_days' => 7]);
        $this->assertNotSame('expired', app(SubscriptionService::class)->forCompany($this->companyId())->state());
        $this->post(route('cost-centers.store'), ['name' => 'Head office'])->assertSessionHasNoErrors();

        // Ended ten days ago: past even the grace.
        $this->hold(['ends_on' => now()->subDays(10)->toDateString()], ['fetched_at' => now()->subDays(3), 'grace_days' => 7]);
        $this->assertSame('expired', app(SubscriptionService::class)->forCompany($this->companyId())->state());
    }

    public function test_a_fresh_licence_gets_no_grace(): void
    {
        $this->hold(['ends_on' => now()->subDay()->toDateString()], ['fetched_at' => now(), 'grace_days' => 7]);

        $this->assertSame('expired', app(SubscriptionService::class)->forCompany($this->companyId())->state());
    }

    public function test_a_block_is_not_softened_by_grace(): void
    {
        $this->hold(['blocked' => true, 'block_reason' => 'Unpaid'], ['fetched_at' => now()->subDays(5)]);

        $this->assertSame('blocked', app(SubscriptionService::class)->forCompany($this->companyId())->state());
    }

    public function test_the_user_limit_in_the_licence_stops_a_new_user(): void
    {
        $this->hold(['max_users' => 1]);
        $before = User::query()->count();

        $this->post(route('users.store'), [
            'name' => 'Second', 'email' => 'second@example.test', 'password' => 'Secret#1234', 'password_confirmation' => 'Secret#1234',
        ])->assertSessionHas('error');

        $this->assertSame($before, User::query()->count());
    }

    public function test_the_banner_comes_from_the_licence(): void
    {
        $this->hold(['ends_on' => now()->addDays(5)->toDateString()]);

        $this->get(route('dashboard'))->assertInertia(fn ($page) => $page
            ->where('subscription.state', 'expiring')->where('subscription.days_left', 5));
    }

    public function test_the_licence_wins_over_a_local_subscription(): void
    {
        $package = Package::create([
            'code' => 'local', 'name' => 'Local', 'modules' => ['hr'], 'price' => 1, 'currency' => 'AFN', 'period_months' => 12, 'is_active' => true,
        ]);
        Subscription::create(['company_id' => $this->companyId(), 'package_id' => $package->id, 'starts_on' => '2026-01-01', 'ends_on' => now()->addYear()->toDateString()]);
        $this->hold(['package' => 'From control', 'modules' => ['expenses']]);

        $this->assertSame('From control', app(SubscriptionService::class)->forCompany($this->companyId())->package->name);
    }

    public function test_without_a_licence_the_local_subscription_still_applies(): void
    {
        $package = Package::create([
            'code' => 'local', 'name' => 'Local', 'modules' => ['hr'], 'price' => 1, 'currency' => 'AFN', 'period_months' => 12, 'is_active' => true,
        ]);
        Subscription::create(['company_id' => $this->companyId(), 'package_id' => $package->id, 'starts_on' => '2026-01-01', 'ends_on' => now()->addYear()->toDateString()]);

        $this->assertSame('Local', app(SubscriptionService::class)->forCompany($this->companyId())->package->name);
    }

    public function test_a_stored_licence_that_no_longer_verifies_is_ignored(): void
    {
        $row = $this->hold(['max_users' => 3]);
        $tampered = $row->payload;
        $tampered['max_users'] = 999;
        $tampered['ends_on'] = '2099-01-01';
        $row->forceFill(['payload' => $tampered])->save();

        $this->assertNull(app(SubscriptionService::class)->forCompany($this->companyId()));
    }

    public function test_a_company_never_sees_another_companys_licence(): void
    {
        $other = $this->branchInAnotherCompany();
        $this->actingInCompany($other->company_id, function () use ($other) {
            $payload = $this->payload(['erp_company_id' => $other->company_id, 'max_users' => 1]);
            CompanyLicense::query()->create([
                'company_id' => $other->company_id, 'api_token' => 't', 'payload' => $payload,
                'signature' => $this->sign($payload), 'license_version' => 1, 'fetched_at' => now(),
            ]);
        });

        $this->assertSame(0, CompanyLicense::query()->count(), 'row level security hides it');
        $this->assertNull(app(SubscriptionService::class)->forCompany($this->companyId()));
    }

    // --- Invoices for the "My subscription" page ---------------------------

    private function feed(): array
    {
        return [
            [
                'number' => 'SUB-2026-0002', 'issued_on' => '2026-03-01', 'due_on' => '2026-03-15', 'period_from' => '2026-03-01', 'period_to' => '2027-03-01',
                'amount' => 1000, 'discount' => 100, 'currency' => 'AFN', 'description' => 'Year 1', 'state' => 'partial', 'paid' => 300, 'balance' => 600,
                'payments' => [['paid_on' => '2026-03-05', 'amount' => 300, 'method' => 'hawala', 'reference' => 'HW-1']],
            ],
            ['number' => 'SUB-2026-0001', 'issued_on' => '2026-01-01', 'amount' => 50, 'currency' => 'AFN', 'state' => 'void', 'paid' => 0, 'balance' => 50],
        ];
    }

    public function test_invoices_are_fetched_and_only_what_a_row_can_show_is_kept(): void
    {
        $this->hold();
        $junk = ['hello' => 'world'];
        $this->fakeHttp(['control.test/api/invoices' => Http::response(['invoices' => array_merge($this->feed(), [$junk, ['number' => 'no-amount']])])]);

        $result = app(LicenseClient::class)->pullInvoices($this->companyId());

        $this->assertTrue($result['ok']);
        $row = CompanyLicense::query()->firstOrFail();
        $this->assertCount(2, $row->invoices, 'rows missing a number, an amount or a date are dropped');
        $this->assertSame('partial', $row->invoices[0]['state']);
        $this->assertSame('hawala', $row->invoices[0]['payments'][0]['method']);
        $this->assertNotNull($row->invoices_fetched_at);
        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer tok-123') && $request->url() === 'https://control.test/api/invoices');
    }

    public function test_an_unknown_state_in_an_invoice_is_not_trusted_as_paid(): void
    {
        $this->hold();
        $this->fakeHttp(['control.test/api/invoices' => Http::response(['invoices' => [
            ['number' => 'X-1', 'issued_on' => '2026-01-01', 'amount' => 10, 'state' => 'definitely-paid'],
        ]])]);

        app(LicenseClient::class)->pullInvoices($this->companyId());

        $this->assertSame('unpaid', CompanyLicense::query()->firstOrFail()->invoices[0]['state']);
    }

    public function test_a_failed_invoice_pull_keeps_the_ones_held_and_says_why(): void
    {
        $this->hold([], ['invoices' => [['number' => 'KEEP-1', 'issued_on' => '2026-01-01', 'amount' => 1, 'currency' => 'AFN', 'state' => 'paid', 'paid' => 1, 'balance' => 0, 'payments' => []]]]);

        $this->fakeHttp(['control.test/*' => Http::response([], 500)]);
        $this->assertFalse(app(LicenseClient::class)->pullInvoices($this->companyId())['ok']);

        $this->fakeHttp(['control.test/*' => Http::response(['invoices' => 'not a list'])]);
        $this->assertFalse(app(LicenseClient::class)->pullInvoices($this->companyId())['ok']);

        $row = CompanyLicense::query()->firstOrFail();
        $this->assertSame('KEEP-1', $row->invoices[0]['number']);
        $this->assertStringStartsWith('Invoices:', $row->last_error);
    }

    public function test_the_pull_command_fetches_the_invoices_after_the_licence_and_not_when_the_licence_failed(): void
    {
        $this->connect();
        $payload = $this->payload();
        $this->fakeHttp([
            'control.test/api/license' => Http::response(['payload' => $payload, 'signature' => $this->sign($payload), 'grace_days' => 7]),
            'control.test/api/invoices' => Http::response(['invoices' => $this->feed()]),
        ]);

        $this->assertSame(0, Artisan::call('license:pull'));
        $this->assertCount(2, CompanyLicense::query()->firstOrFail()->invoices);
        Http::assertSentCount(2);

        // The licence itself failing: the invoices are not even asked for.
        $this->fakeHttp(['control.test/api/license' => Http::response([], 500)]);
        $this->assertSame(1, Artisan::call('license:pull'));
        Http::assertSentCount(1);
    }

    public function test_an_invoice_problem_does_not_make_the_licence_pull_fail(): void
    {
        $this->connect();
        $payload = $this->payload();
        $this->fakeHttp([
            'control.test/api/license' => Http::response(['payload' => $payload, 'signature' => $this->sign($payload), 'grace_days' => 7]),
            'control.test/api/invoices' => Http::response([], 500),
        ]);

        $this->assertSame(0, Artisan::call('license:pull'));
        $this->assertNotNull(CompanyLicense::query()->firstOrFail()->payload);
    }

    public function test_my_subscription_shows_the_control_centers_invoices_not_the_old_local_ones(): void
    {
        // An old local invoice that must not show once the company is connected.
        $package = Package::create(['code' => 'local', 'name' => 'Local', 'modules' => [], 'price' => 1, 'currency' => 'AFN', 'period_months' => 12, 'is_active' => true]);
        $local = Subscription::create(['company_id' => $this->companyId(), 'package_id' => $package->id, 'starts_on' => '2026-01-01', 'ends_on' => now()->addYear()->toDateString()]);
        app(SubscriptionService::class)->invoice($local, ['issued_on' => '2026-02-01', 'amount' => 777]);

        $this->hold([], ['invoices' => $this->feed()]);

        $this->get(route('subscription.show'))->assertOk()->assertInertia(fn ($page) => $page
            ->component('Workspace/Subscription')
            ->has('invoices', 2)
            ->where('invoices.0.number', 'SUB-2026-0002')
            ->where('invoices.0.state', 'partial')
            ->where('invoices.0.balance', 600)
            ->where('invoices.0.payments.0.method', 'hawala')
            ->where('invoices.1.state', 'void'));
    }

    public function test_the_feed_is_used_only_while_the_licence_itself_is_accepted(): void
    {
        $package = Package::create(['code' => 'local', 'name' => 'Local', 'modules' => [], 'price' => 1, 'currency' => 'AFN', 'period_months' => 12, 'is_active' => true]);
        $local = Subscription::create(['company_id' => $this->companyId(), 'package_id' => $package->id, 'starts_on' => '2026-01-01', 'ends_on' => now()->addYear()->toDateString()]);
        app(SubscriptionService::class)->invoice($local, ['issued_on' => '2026-02-01', 'amount' => 777]);

        $row = $this->hold([], ['invoices' => $this->feed()]);
        $tampered = $row->payload;
        $tampered['max_users'] = 999;
        $row->forceFill(['payload' => $tampered])->save(); // the signature no longer matches

        // The licence is ignored (the local subscription applies), so the local invoices are shown, not the feed.
        $this->get(route('subscription.show'))->assertInertia(fn ($page) => $page
            ->has('invoices', 1)->where('invoices.0.amount', 777)->where('plan.package', 'Local'));
    }

    public function test_a_connected_company_whose_invoices_are_not_fetched_yet_sees_none_rather_than_the_old_ones(): void
    {
        $package = Package::create(['code' => 'local', 'name' => 'Local', 'modules' => [], 'price' => 1, 'currency' => 'AFN', 'period_months' => 12, 'is_active' => true]);
        $local = Subscription::create(['company_id' => $this->companyId(), 'package_id' => $package->id, 'starts_on' => '2026-01-01', 'ends_on' => now()->addYear()->toDateString()]);
        app(SubscriptionService::class)->invoice($local, ['issued_on' => '2026-02-01', 'amount' => 777]);
        $this->hold(); // licence held, invoices never fetched

        $this->get(route('subscription.show'))->assertInertia(fn ($page) => $page->has('invoices', 0));
    }

    // --- The token must not cross the network in the clear -----------------

    public function test_in_production_an_address_that_is_not_https_is_refused_by_every_call_and_nothing_is_sent(): void
    {
        $this->hold();
        $this->app->detectEnvironment(fn () => 'production');
        config(['control.url' => 'http://control.test']);
        $this->fakeHttp(['*' => Http::response(['payload' => [], 'signature' => 'x', 'invoices' => []])]);

        $client = app(LicenseClient::class);
        foreach ([$client->pull($this->companyId()), $client->pullInvoices($this->companyId()), $client->reportUsage($this->companyId())] as $result) {
            $this->assertFalse($result['ok']);
            $this->assertStringContainsString('must start with https://', $result['message']);
        }

        Http::assertNothingSent();
        $this->assertStringContainsString('https://', CompanyLicense::query()->firstOrFail()->last_error);
    }

    public function test_https_is_accepted_in_production_whatever_its_case_and_a_plain_address_is_fine_in_development(): void
    {
        $this->connect();
        $payload = $this->payload();
        $this->fakeControl($payload);

        $this->app->detectEnvironment(fn () => 'production');
        config(['control.url' => 'HTTPS://control.test']);
        $this->assertTrue(app(LicenseClient::class)->pull($this->companyId())['ok']);

        $this->app->detectEnvironment(fn () => 'local');
        config(['control.url' => 'http://control.test']);
        $this->assertTrue(app(LicenseClient::class)->pull($this->companyId())['ok'], 'plain http is allowed outside production');
    }

    public function test_a_redirect_is_not_followed_so_the_token_cannot_be_sent_elsewhere(): void
    {
        $this->connect();
        $this->fakeHttp([
            'control.test/api/license' => Http::response('', 302, ['Location' => 'https://elsewhere.test/steal']),
            'elsewhere.test/*' => Http::response(['payload' => [], 'signature' => 'x']),
        ]);

        $result = app(LicenseClient::class)->pull($this->companyId());

        $this->assertFalse($result['ok']);
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_contains($request->url(), 'elsewhere.test'));
    }

    // --- Reporting usage ---------------------------------------------------

    public function test_usage_is_reported_with_the_token_and_only_counters(): void
    {
        $this->connect();
        $this->fakeHttp(['control.test/api/usage' => Http::response(['ok' => true])]);

        $result = app(LicenseClient::class)->reportUsage($this->companyId());

        $this->assertTrue($result['ok']);
        Http::assertSent(function ($request) {
            return $request->hasHeader('Authorization', 'Bearer tok-123')
                && array_keys($request->data()) === ['users', 'branches', 'erp_version']
                && $request['users'] === 1;
        });
    }

    public function test_a_company_that_is_not_connected_reports_nothing(): void
    {
        $this->fakeHttp();

        $this->assertFalse(app(LicenseClient::class)->reportUsage($this->companyId())['ok']);
        Http::assertNothingSent();
    }
}
