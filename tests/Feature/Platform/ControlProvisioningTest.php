<?php

namespace Tests\Feature\Platform;

use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Platform\CompanyLicense;
use App\Models\User;
use App\Services\Control\LicenseVerifier;
use App\Support\CompanyDatabaseContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\Support\BuildsErpContext;
use Tests\TestCase;

/**
 * The control center creating a company for one of its customers.
 *
 * The door lets someone make an administrator account, so most of what is
 * tested here is that it opens for the control center's signature and for
 * nothing else, and that it never leaves half a company behind.
 */
class ControlProvisioningTest extends TestCase
{
    use BuildsErpContext;
    use RefreshDatabase;

    private const CUSTOMER = '01M6DTM02SCYWQ21QAJD5NYKH0';

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
            'control.provisioning' => true,
        ]);

        // The licence fetch right after creation has nothing to talk to.
        Http::swap(new Factory());
        Http::fake(['control.test/*' => Http::response('down', 503)]);
        Cache::flush();

        // A request from the control center arrives with no user and no company
        // chosen: nothing is bound in the container and the connection acts for
        // no one. The test's own context, built above, would hide a step that
        // depended on one.
        foreach (['active_company_id', 'accessible_company_ids', 'active_branch_id'] as $binding) {
            app()->forgetInstance($binding);
        }
        CompanyDatabaseContext::forget();
        Auth::forgetGuards();
    }

    /** @return array<string, mixed> */
    private function body(array $overrides = []): array
    {
        return array_replace_recursive([
            'customer_id' => self::CUSTOMER,
            'token' => str_repeat('a', 40),
            'company' => [
                'name_en' => 'Kabul Traders',
                'name_fa' => 'تاجران کابل',
                'name_pa' => 'د کابل سوداګر',
                'phone' => '0700000000',
                'city' => 'Kabul',
                'country' => 'Afghanistan',
                'calendar_type' => 'jalali',
                'business_type' => 'retail',
                'locale' => 'fa',
                'base_currency' => 'AFN',
            ],
            'admin' => ['name' => 'Ahmad Noori', 'email' => 'ahmad@kabul-traders.test'],
        ], $overrides);
    }

    /**
     * Sends a request signed like the control center signs it. `$tamper`
     * changes the body after signing; `$sign` can be replaced to sign badly.
     */
    private function send(array $body, array $headers = [], ?callable $tamper = null, ?string $key = null)
    {
        $json = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $timestamp = (string) now()->timestamp;
        $nonce = bin2hex(random_bytes(16));

        $signature = base64_encode(sodium_crypto_sign_detached(
            "provision.v1\n{$timestamp}\n{$nonce}\n{$json}",
            $key ?? $this->secretKey,
        ));

        $headers = array_replace([
            'X-Control-Timestamp' => $timestamp,
            'X-Control-Nonce' => $nonce,
            'X-Control-Signature' => $signature,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ], $headers);

        if ($tamper) {
            $json = $tamper($json);
        }

        $server = [];
        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }
        $server['CONTENT_TYPE'] = 'application/json';

        return $this->call('POST', '/api/control/provision', [], [], [], $server, $json);
    }

    private function companiesMade(): int
    {
        return Company::query()->withoutGlobalScopes()->where('control_customer_id', self::CUSTOMER)->count();
    }

    private function assertNothingMade(): void
    {
        $this->assertSame(0, $this->companiesMade());
        $this->assertSame(0, User::query()->where('email', 'ahmad@kabul-traders.test')->count());
    }

    // --- A good request -----------------------------------------------------

    public function test_a_signed_request_makes_the_company_its_branch_and_its_administrator(): void
    {
        $response = $this->send($this->body());

        $response->assertCreated()->assertJsonPath('created', true);
        $companyId = $response->json('company_id');

        $company = Company::query()->withoutGlobalScopes()->findOrFail($companyId);
        $this->assertSame(self::CUSTOMER, $company->control_customer_id);
        $this->assertSame('Kabul Traders', $company->name_en);
        $this->assertSame('د کابل سوداګر', $company->name_pa);
        $this->assertSame('normal', $company->working_style->value ?? $company->working_style);

        CompanyDatabaseContext::runFor($companyId, function () use ($response, $companyId) {
            $branch = Branch::query()->findOrFail($response->json('branch_id'));
            $this->assertTrue((bool) $branch->is_main);
            $this->assertSame($companyId, $branch->company_id);

            $license = CompanyLicense::query()->firstOrFail();
            $this->assertSame(self::CUSTOMER, $license->control_customer_id);
            $this->assertSame(str_repeat('a', 40), $license->api_token);
        });

        // The token is stored encrypted, not as given.
        $this->assertNotSame(str_repeat('a', 40), DB::table('company_licenses')->where('company_id', $companyId)->value('api_token'));
    }

    public function test_the_administrator_is_verified_runs_only_that_company_and_has_a_password_nobody_knows(): void
    {
        $companyId = $this->send($this->body())->json('company_id');

        $user = User::query()->where('email', 'ahmad@kabul-traders.test')->firstOrFail();
        $this->assertNotNull($user->email_verified_at);
        $this->assertSame($companyId, $user->company_id);

        setPermissionsTeamId($companyId);
        try {
            $this->assertTrue($user->unsetRelation('roles')->hasRole('super-admin'));
        } finally {
            setPermissionsTeamId(null);
        }

        foreach (['password', 'Password1', 'ahmad@kabul-traders.test', 'Kabul Traders', ''] as $guess) {
            $this->assertFalse(Hash::check($guess, $user->password));
        }
    }

    public function test_the_company_is_invisible_to_every_other_company(): void
    {
        $companyId = $this->send($this->body())->json('company_id');

        // This test's own company sees no trace of it.
        $this->actingInCompany($this->ctx['company']->id, function () {
            $this->assertSame(0, CompanyLicense::query()->count());
        });

        CompanyDatabaseContext::runFor($companyId, function () {
            $this->assertSame(1, CompanyLicense::query()->count());
        });
    }

    public function test_the_licence_is_fetched_at_once_when_the_control_center_answers(): void
    {
        $companyId = null;
        $this->app->bind('provision.hook', fn () => null);

        Http::swap(new Factory());
        Http::fake(function ($request) use (&$companyId) {
            if (! str_ends_with($request->url(), '/api/license')) {
                return Http::response(['invoices' => []], 200);
            }
            $payload = [
                'v' => 1, 'customer_id' => self::CUSTOMER, 'erp_company_id' => null, 'license_version' => 3,
                'issued_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'customer_status' => 'active', 'state' => 'trial',
                'kind' => 'trial', 'package' => 'Starter', 'starts_on' => now()->toDateString(),
                'ends_on' => now()->addDays(14)->toDateString(), 'blocked' => false, 'writes_allowed' => true,
                'block_reason' => null, 'modules' => [], 'max_users' => 3, 'max_branches' => 1,
                'max_storage_mb' => null, 'max_monthly_documents' => null,
            ];

            return Http::response([
                'payload' => $payload,
                'signature' => base64_encode(sodium_crypto_sign_detached(LicenseVerifier::canonical($payload), $this->secretKey)),
                'grace_days' => 7,
            ], 200);
        });

        $response = $this->send($this->body());

        $response->assertCreated()->assertJsonPath('license_ok', true);
        CompanyDatabaseContext::runFor($response->json('company_id'), function () {
            $this->assertSame(3, CompanyLicense::query()->firstOrFail()->license_version);
        });
    }

    public function test_the_company_is_made_even_when_the_control_center_cannot_be_reached_just_then(): void
    {
        $response = $this->send($this->body());

        $response->assertCreated()->assertJsonPath('license_ok', false);
        $this->assertSame(1, $this->companiesMade());
    }

    // --- The door stays shut ------------------------------------------------

    public function test_it_is_off_unless_switched_on(): void
    {
        config(['control.provisioning' => false]);

        $this->send($this->body())->assertNotFound();
        $this->assertNothingMade();
    }

    public function test_an_unsigned_request_is_refused(): void
    {
        $this->send($this->body(), ['X-Control-Signature' => ''])->assertUnauthorized();
        $this->send($this->body(), ['X-Control-Nonce' => ''])->assertUnauthorized();
        $this->send($this->body(), ['X-Control-Timestamp' => ''])->assertUnauthorized();
        $this->assertNothingMade();
    }

    public function test_a_request_signed_with_another_key_is_refused(): void
    {
        $other = sodium_crypto_sign_secretkey(sodium_crypto_sign_keypair());

        $this->send($this->body(), key: $other)->assertUnauthorized();
        $this->assertNothingMade();
    }

    public function test_a_body_changed_after_signing_is_refused(): void
    {
        $this->send(
            $this->body(),
            tamper: fn (string $json) => str_replace('ahmad@kabul-traders.test', 'attacker@evil.test', $json),
        )->assertUnauthorized();

        $this->assertNothingMade();
        $this->assertSame(0, User::query()->where('email', 'attacker@evil.test')->count());
    }

    public function test_a_request_too_old_or_from_the_future_is_refused(): void
    {
        // The signature covers the timestamp, so the header and the signed
        // value are changed together, as a real late request would be.
        foreach ([-301, 301] as $offset) {
            $timestamp = (string) (now()->timestamp + $offset);
            $nonce = bin2hex(random_bytes(16));
            $json = json_encode($this->body(), JSON_UNESCAPED_UNICODE);
            $signature = base64_encode(sodium_crypto_sign_detached("provision.v1\n{$timestamp}\n{$nonce}\n{$json}", $this->secretKey));

            $this->call('POST', '/api/control/provision', [], [], [], [
                'HTTP_X_CONTROL_TIMESTAMP' => $timestamp,
                'HTTP_X_CONTROL_NONCE' => $nonce,
                'HTTP_X_CONTROL_SIGNATURE' => $signature,
                'CONTENT_TYPE' => 'application/json',
                'HTTP_ACCEPT' => 'application/json',
            ], $json)->assertUnauthorized();
        }

        $this->assertNothingMade();
    }

    public function test_the_same_request_cannot_be_replayed(): void
    {
        $json = json_encode($this->body(), JSON_UNESCAPED_UNICODE);
        $timestamp = (string) now()->timestamp;
        $nonce = bin2hex(random_bytes(16));
        $headers = [
            'HTTP_X_CONTROL_TIMESTAMP' => $timestamp,
            'HTTP_X_CONTROL_NONCE' => $nonce,
            'HTTP_X_CONTROL_SIGNATURE' => base64_encode(sodium_crypto_sign_detached("provision.v1\n{$timestamp}\n{$nonce}\n{$json}", $this->secretKey)),
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
        ];

        $this->call('POST', '/api/control/provision', [], [], [], $headers, $json)->assertCreated();
        $this->call('POST', '/api/control/provision', [], [], [], $headers, $json)->assertStatus(409);

        $this->assertSame(1, $this->companiesMade());
    }

    public function test_a_signature_that_fails_does_not_use_up_its_nonce(): void
    {
        $nonce = bin2hex(random_bytes(16));

        $this->send($this->body(), ['X-Control-Nonce' => $nonce, 'X-Control-Signature' => base64_encode(str_repeat('x', 64))])->assertUnauthorized();

        $this->assertFalse(Cache::has('control-provision-nonce:'.$nonce));
    }

    public function test_production_refuses_plain_http(): void
    {
        $this->app['env'] = 'production';

        $this->send($this->body())->assertForbidden();
        $this->assertNothingMade();
    }

    public function test_without_a_public_key_nothing_is_accepted(): void
    {
        config(['control.public_key' => null]);

        $this->send($this->body())->assertUnauthorized();
        $this->assertNothingMade();
    }

    // --- Once per customer --------------------------------------------------

    public function test_asking_twice_for_the_same_customer_gives_the_same_company(): void
    {
        $first = $this->send($this->body());
        $second = $this->send($this->body(['token' => str_repeat('b', 40)]));

        $first->assertCreated();
        $second->assertOk()->assertJsonPath('created', false)->assertJsonPath('company_id', $first->json('company_id'));
        $this->assertSame(1, $this->companiesMade());
        $this->assertSame(1, User::query()->where('email', 'ahmad@kabul-traders.test')->count());

        // The latest token the control center sent is the one that counts.
        CompanyDatabaseContext::runFor($first->json('company_id'), function () {
            $this->assertSame(str_repeat('b', 40), CompanyLicense::query()->firstOrFail()->api_token);
        });
    }

    public function test_a_second_customer_gets_a_second_company(): void
    {
        $this->send($this->body())->assertCreated();
        $other = $this->send($this->body([
            'customer_id' => '01M6DTM02SCYWQ21QAJD5NYKH1',
            'admin' => ['email' => 'other@kabul-traders.test'],
        ]));

        $other->assertCreated();
        $this->assertSame(2, Company::query()->withoutGlobalScopes()->whereNotNull('control_customer_id')->count());
    }

    // --- Bad data -----------------------------------------------------------

    public function test_an_email_that_belongs_to_a_user_is_refused_and_nothing_is_made(): void
    {
        $taken = User::factory()->create(['email' => 'ahmad@kabul-traders.test']);

        $this->send($this->body())->assertStatus(422)->assertJsonValidationErrors(['admin.email']);
        $this->assertSame(0, $this->companiesMade());
        $this->assertSame(1, User::query()->where('email', $taken->email)->count());
    }

    public function test_invalid_details_get_a_clear_message_not_a_server_error(): void
    {
        foreach ([
            ['company' => ['base_currency' => 'XXX']],
            ['company' => ['calendar_type' => 'martian']],
            ['company' => ['locale' => 'klingon']],
            ['company' => ['business_type' => 'nope']],
            ['admin' => ['email' => 'not-an-email']],
            ['token' => 'short'],
            ['customer_id' => 'abc'],
        ] as $overrides) {
            $this->send($this->body($overrides))->assertStatus(422);
        }

        $this->assertNothingMade();
    }

    public function test_missing_details_name_what_is_missing(): void
    {
        $body = $this->body();
        unset($body['company']['name_en'], $body['admin']['name']);

        $this->send($body)->assertStatus(422)->assertJsonValidationErrors(['company.name_en', 'admin.name']);
        $this->assertNothingMade();
    }

    // --- All or nothing -----------------------------------------------------

    public function test_a_failure_part_way_leaves_no_company_no_user_and_no_token(): void
    {
        CompanyLicense::creating(fn () => throw new RuntimeException('boom'));

        $this->send($this->body())->assertStatus(500);

        CompanyLicense::flushEventListeners();
        $this->assertNothingMade();
        $this->assertSame(0, Company::query()->withoutGlobalScopes()->where('name_en', 'Kabul Traders')->count());
    }
}
