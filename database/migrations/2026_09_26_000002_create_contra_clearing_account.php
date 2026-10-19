<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * The account a set-off passes through.
 *
 * Offsetting what a party owes us against what we owe them is posted as two
 * settlement vouchers, not one journal entry — the customer's invoices are
 * relieved and the supplier's bills are paid, and each side needs its own
 * voucher to get its own settlement rows. This account is what the two halves
 * hand between them, and it is always left at zero. A balance sitting here
 * means half an offset went missing, which is exactly the kind of error
 * nobody would spot on either party's statement.
 *
 * Account::defaultAccounts() seeds it for new companies; this brings companies
 * provisioned earlier in line. Idempotent.
 */
return new class extends Migration
{
    private const DEFINITION = [
        'slug' => 'contra-clearing',
        'name' => 'Set-off Clearing',
        'local_name' => 'تهاتر',
        'number' => '3070',
        'account_type_slug' => 'other-current-asset',
        'remark' => 'Passes between the two halves of a set-off; always nets to zero',
    ];

    public function up(): void
    {
        // accounts.created_by is NOT NULL with a foreign key, so provisioning
        // needs a real user to attribute the row to.
        $actorId = DB::table('users')->orderBy('created_at')->value('id');

        if (! $actorId) {
            return;
        }

        DB::transaction(function () use ($actorId) {
            foreach (DB::table('companies')->pluck('id') as $companyId) {
                $this->createFor((string) $companyId, (string) $actorId);
            }
        });
    }

    private function createFor(string $companyId, string $actorId): void
    {
        $exists = DB::table('accounts')
            ->where('company_id', $companyId)
            ->where('slug', self::DEFINITION['slug'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        $typeId = DB::table('account_types')
            ->where('company_id', $companyId)
            ->where('slug', self::DEFINITION['account_type_slug'])
            ->whereNull('deleted_at')
            ->value('id');

        if (! $typeId) {
            Log::warning('Skipped set-off clearing account: company has no other-current-asset account type.', [
                'company_id' => $companyId,
            ]);

            return;
        }

        DB::table('accounts')->insert([
            'id' => (string) Str::ulid(),
            'name' => $this->availableName($companyId, self::DEFINITION['name']),
            'local_name' => self::DEFINITION['local_name'],
            'number' => $this->availableNumber($companyId, self::DEFINITION['number']),
            'account_type_id' => $typeId,
            'parent_id' => null,
            'is_active' => true,
            'is_main' => true,
            'slug' => self::DEFINITION['slug'],
            'company_id' => $companyId,
            'remark' => self::DEFINITION['remark'],
            'created_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Account numbers are unique per company and users add their own, so the
     * preferred number may already be in use. Walk forward rather than fail the
     * migration over a number nobody posts against.
     */
    private function availableNumber(string $companyId, string $preferred): string
    {
        $number = $preferred;

        for ($suffix = 1; $suffix <= 50; $suffix++) {
            $taken = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('number', $number)
                ->whereNull('deleted_at')
                ->exists();

            if (! $taken) {
                return $number;
            }

            $number = (string) ((int) $preferred + $suffix);
        }

        return $preferred . '-' . Str::lower(Str::random(4));
    }

    /** Names are unique per company too. */
    private function availableName(string $companyId, string $preferred): string
    {
        $name = $preferred;

        for ($suffix = 2; $suffix <= 50; $suffix++) {
            $taken = DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('name', $name)
                ->whereNull('deleted_at')
                ->exists();

            if (! $taken) {
                return $name;
            }

            $name = $preferred . ' ' . $suffix;
        }

        return $preferred . ' ' . Str::upper(Str::random(4));
    }

    public function down(): void
    {
        // Left in place: dropping it would orphan every set-off already posted
        // through it.
    }
};
