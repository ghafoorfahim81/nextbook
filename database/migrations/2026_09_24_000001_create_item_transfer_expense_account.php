<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Item transfers now post their freight to a named account instead of the
 * catch-all "Other Expenses", so the cost of moving stock between the
 * company's own warehouses can be read off the P&L on its own line.
 *
 * ItemTransferService resolves it by SLUG — the name is localised into Dari
 * and Pashto, so a name lookup breaks the moment someone switches language.
 * This brings companies that were provisioned before the account existed in
 * line with the one Account::defaultAccounts() now seeds.
 *
 * Idempotent: a company that already answers to the slug is left alone.
 */
return new class extends Migration
{
    private const DEFINITION = [
        'slug' => 'item-transfer-expense',
        'name' => 'Item Transfer Expense',
        'local_name' => 'هزینه انتقال جنس',
        'number' => '9801',
        'account_type_slug' => 'expense',
        'parent_slug' => 'other-expenses',
        'remark' => 'Cost of moving goods between own warehouses',
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
            Log::warning('Skipped item transfer expense account: company has no expense account type.', [
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
            'parent_id' => DB::table('accounts')
                ->where('company_id', $companyId)
                ->where('slug', self::DEFINITION['parent_slug'])
                ->whereNull('deleted_at')
                ->value('id'),
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
        // Left in place: dropping it would orphan every transfer that already
        // posted its freight here.
    }
};
