<?php

namespace App\Services\Control;

use App\Models\Platform\CompanyLicense;
use App\Services\SubscriptionService;
use App\Support\CompanyDatabaseContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * The ERP's side of the conversation with the control center: ask for the
 * company's licence, and report what the company uses.
 *
 * A licence is accepted only when its signature verifies, it is for this
 * company, and it is not older than the one already held (a late reply cannot
 * undo a block or a renewal that came after it). A failure of any kind keeps
 * the licence the company already has and records why; it never takes anything
 * away.
 *
 * Both calls run inside the company's own row level security context, since
 * they are made from a command, outside a request.
 */
class LicenseClient
{
    /** @return array{ok: bool, message: string} */
    public function pull(string $companyId): array
    {
        return CompanyDatabaseContext::runFor($companyId, function () use ($companyId) {
            $row = CompanyLicense::query()->where('company_id', $companyId)->first();
            if (! $row || ! $row->api_token) {
                return ['ok' => false, 'message' => 'This company is not connected (run license:connect).'];
            }

            $fail = function (string $message) use ($row): array {
                $row->forceFill(['last_attempt_at' => now(), 'last_error' => $message])->save();

                return ['ok' => false, 'message' => $message];
            };

            if (! config('control.url') || ! config('control.public_key')) {
                return $fail('CONTROL_URL and CONTROL_PUBLIC_KEY must be set.');
            }

            try {
                $response = Http::withToken($row->api_token)->acceptJson()
                    ->timeout((int) config('control.timeout'))
                    ->get(rtrim((string) config('control.url'), '/').'/api/license');
            } catch (ConnectionException) {
                return $fail('The control center could not be reached.');
            }

            if (! $response->successful()) {
                return $fail("The control center answered {$response->status()}.");
            }

            $payload = $response->json('payload');
            $signature = $response->json('signature');
            if (! is_array($payload) || ! is_string($signature)) {
                return $fail('The reply was not a licence.');
            }

            if (! LicenseVerifier::verify($payload, $signature, config('control.public_key'))) {
                return $fail('The licence signature does not verify.');
            }

            if (! empty($payload['erp_company_id']) && $payload['erp_company_id'] !== $companyId) {
                return $fail('The licence is for another company.');
            }
            if ($row->control_customer_id && ($payload['customer_id'] ?? null) !== $row->control_customer_id) {
                return $fail('The licence is for another customer.');
            }
            if (($payload['license_version'] ?? 0) < $row->license_version) {
                return $fail('A licence older than the one held was ignored.');
            }

            $row->forceFill([
                'control_customer_id' => $payload['customer_id'],
                'payload' => $payload,
                'signature' => $signature,
                'license_version' => $payload['license_version'],
                'grace_days' => (int) ($response->json('grace_days') ?? $row->grace_days),
                'fetched_at' => now(),
                'last_attempt_at' => now(),
                'last_error' => null,
            ])->save();

            return ['ok' => true, 'message' => "Licence version {$payload['license_version']} received."];
        });
    }

    /** @return array{ok: bool, message: string} */
    public function reportUsage(string $companyId): array
    {
        return CompanyDatabaseContext::runFor($companyId, function () use ($companyId) {
            $row = CompanyLicense::query()->where('company_id', $companyId)->first();
            if (! $row || ! $row->api_token || ! config('control.url')) {
                return ['ok' => false, 'message' => 'This company is not connected.'];
            }

            $usage = app(SubscriptionService::class)->usage($companyId);

            try {
                $response = Http::withToken($row->api_token)->acceptJson()
                    ->timeout((int) config('control.timeout'))
                    ->post(rtrim((string) config('control.url'), '/').'/api/usage', [
                        'users' => $usage['users'],
                        'branches' => $usage['branches'],
                        'erp_version' => config('app.version'),
                    ]);
            } catch (ConnectionException) {
                return ['ok' => false, 'message' => 'The control center could not be reached.'];
            }

            return $response->successful()
                ? ['ok' => true, 'message' => 'Usage reported.']
                : ['ok' => false, 'message' => "The control center answered {$response->status()}."];
        });
    }
}
