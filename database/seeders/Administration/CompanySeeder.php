<?php

namespace Database\Seeders\Administration;

use App\Models\Administration\Company;
use Illuminate\Database\Seeder;
use App\Enums\CalendarType;
use App\Enums\BusinessType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use Symfony\Component\Uid\Ulid;
use App\Models\Administration\Currency;

/**
 * Seeds the single default company.
 *
 * This runs before every other seeder, because branches — and through them
 * every scoped table — require a company. It therefore cannot resolve a home
 * currency on its first pass: currencies are company-scoped and are created
 * later by CurrencySeeder.
 *
 * So the seeder is idempotent and is called twice. The first pass creates the
 * company with a null currency; the second, after CurrencySeeder, fills the
 * home currency in. Re-running it at any point is a no-op beyond filling in
 * whatever is still missing.
 */
class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $company = Company::withoutGlobalScopes()->where('name_en', 'NextBook')->first();

        if (! $company) {
            $company = Company::create([
                'id' => (string) new Ulid(),
                'name_en' => 'NextBook',
                'name_fa' => 'نکست بوک همراه',
                'name_pa' => 'نکست بوک همراه',
                'abbreviation' => 'NB',
                'address' => '123 Main St, Anytown, USA',
                'phone' => '03123456789',
                'country' => 'Pakistan',
                'city' => 'Karachi',
                'logo' => 'logo.png',
                'calendar_type' => CalendarType::GREGORIAN,
                'locale' => Locale::EN,
                'working_style' => WorkingStyle::NORMAL,
                'business_type' => BusinessType::SUPERMARKET,
                'currency_id' => null,
            ]);
        }

        $this->linkHomeCurrency($company);
        $this->linkAdminUser($company);
    }

    /**
     * Set the company's home currency once one exists. Prefers the currency
     * explicitly flagged as base, falling back to whichever was seeded first.
     */
    private function linkHomeCurrency(Company $company): void
    {
        if ($company->currency_id) {
            return;
        }

        $currencyId = Currency::withoutGlobalScopes()->where('is_base_currency', true)->value('id')
            ?? Currency::withoutGlobalScopes()->value('id');

        if ($currencyId) {
            $company->currency_id = $currencyId;
            $company->save();
        }
    }

    /**
     * Attach the admin user, who may be seeded before or after this runs.
     */
    private function linkAdminUser(Company $company): void
    {
        $user = \App\Models\User::withoutGlobalScopes()->where('name', 'admin')->first();

        if ($user && ! $user->company_id) {
            $user->company_id = $company->id;
            $user->save();
        }
    }
}
