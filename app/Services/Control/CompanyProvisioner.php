<?php

namespace App\Services\Control;

use App\Enums\CostingMethod;
use App\Models\Administration\Branch;
use App\Models\Administration\Company;
use App\Models\Platform\CompanyLicense;
use App\Models\User;
use App\Services\CompanySetupService;
use App\Support\CompanyDatabaseContext;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Makes a company for a customer of the control center.
 *
 * It does not build a company itself: it goes through CompanySetupService, the
 * one path sign-up and the super-admin screen use, so the company has the same
 * branch, chart of accounts, currencies and units as any other. What it adds is
 * around that:
 *
 *  - one company per customer: asked again for the same customer it returns the
 *    company it already made and creates nothing (the unique customer id on
 *    `companies` settles two requests arriving together);
 *  - the first administrator, with a random password nobody knows, so the
 *    password never travels through the control center: the person sets their
 *    own with the ordinary "forgot password" page;
 *  - the access token, stored encrypted, so the company can fetch its licence;
 *  - all of it in one transaction: if any step fails nothing is left behind.
 *
 * The licence is fetched afterwards, outside the transaction. If the control
 * center cannot be reached at that moment the company still exists and the
 * scheduled license:pull gets the licence within hours.
 */
class CompanyProvisioner
{
    public function __construct(
        private readonly CompanySetupService $setup,
        private readonly LicenseClient $client,
    ) {
    }

    /**
     * @param  array<string, mixed>  $data  validated: customer_id, token, company, admin
     * @return array{created: bool, company: Company, branch_id: string, license: array{ok: bool, message: string}}
     */
    public function provision(array $data): array
    {
        try {
            $result = $this->create($data);
        } catch (UniqueConstraintViolationException $e) {
            // Either a request for the same customer won the race, or the
            // administrator's e-mail is taken. Only the first is answered.
            if (! $this->existing($data['customer_id'])) {
                throw $e;
            }
            $result = null;
        }

        $created = $result !== null;
        $company = $result ?? $this->existing($data['customer_id']);

        if (! $created) {
            $this->refreshToken($company, $data['token']);
        }

        $license = $this->client->pull($company->id);
        if ($license['ok']) {
            $this->client->pullInvoices($company->id);
        }

        return [
            'created' => $created,
            'company' => $company,
            'branch_id' => $this->mainBranchId($company),
            'license' => $license,
        ];
    }

    public function existing(string $customerId): ?Company
    {
        return Company::query()->withoutGlobalScopes()->where('control_customer_id', $customerId)->first();
    }

    private function create(array $data): ?Company
    {
        if ($this->existing($data['customer_id'])) {
            return null;
        }

        return DB::transaction(function () use ($data): Company {
            $admin = $this->makeAdministrator($data['admin'], $data['company']['locale']);

            $attributes = $data['company'];
            $baseCurrency = $attributes['base_currency'];
            unset($attributes['base_currency']);
            $attributes += ['working_style' => 'normal', 'costing_method' => CostingMethod::FIFO->value];

            $company = $this->setup->create($attributes, $baseCurrency, $admin)['company'];

            $company->forceFill(['control_customer_id' => $data['customer_id']])->save();

            CompanyDatabaseContext::runFor($company->id, fn () => CompanyLicense::query()->create([
                'company_id' => $company->id,
                'control_customer_id' => $data['customer_id'],
                'api_token' => $data['token'],
            ]));

            return $company;
        });
    }

    private function makeAdministrator(array $admin, string $locale): User
    {
        $user = new User([
            'name' => $admin['name'],
            'email' => $admin['email'],
            'locale' => $locale,
            // Unknowable, so the account cannot be signed in to until its owner
            // chooses a password through the reset link.
            'password' => Hash::make(Str::random(64)),
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }

    /** The control center issues one current token; the latest one it sent is the valid one. */
    private function refreshToken(Company $company, string $token): void
    {
        CompanyDatabaseContext::runFor($company->id, function () use ($company, $token) {
            $row = CompanyLicense::query()->firstOrNew(['company_id' => $company->id]);
            if ($row->api_token !== $token) {
                $row->forceFill(['api_token' => $token, 'control_customer_id' => $company->control_customer_id, 'last_error' => null])->save();
            }
        });
    }

    private function mainBranchId(Company $company): string
    {
        return CompanyDatabaseContext::runFor($company->id, fn () => (string) Branch::query()
            ->where('company_id', $company->id)->where('is_main', true)->value('id'));
    }
}
