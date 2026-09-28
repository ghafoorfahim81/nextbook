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
 * Account::defaultAccounts() seeds it for new branches; this brings branches
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
            foreach (DB::table('branches')->pluck('id') as $branchId) {
                $this->createFor((string) $branchId, (string) $actorId);
            }
        });
    }

    private function createFor(string $branchId, string $actorId): void
    {
        $exists = DB::table('accounts')
            ->where('branch_id', $branchId)
            ->where('slug', self::DEFINITION['slug'])
            ->whereNull('deleted_at')
            ->exists();

        if ($exists) {
            return;
        }

        $typeId = DB::table('account_types')
            ->where('branch_id', $branchId)
            ->where('slug', self::DEFINITION['account_type_slug'])
            ->whereNull('deleted_at')
            ->value('id');

        if (! $typeId) {
            Log::warning('Skipped set-off clearing account: branch has no other-current-asset account type.', [
                'branch_id' => $branchId,
            ]);

            return;
        }

        DB::table('accounts')->insert([
            'id' => (string) Str::ulid(),
            'name' => $this->availableName($branchId, self::DEFINITION['name']),
            'local_name' => self::DEFINITION['local_name'],
            'number' => $this->availableNumber($branchId, self::DEFINITION['number']),
            'account_type_id' => $typeId,
            'parent_id' => null,
            'is_active' => true,
            'is_main' => true,
            'slug' => self::DEFINITION['slug'],
            'branch_id' => $branchId,
            'remark' => self::DEFINITION['remark'],
            'created_by' => $actorId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Account numbers are unique per branch and users add their own, so the
     * preferred number may already be in use. Walk forward rather than fail the
     * migration over a number nobody posts against.
     */
    private function availableNumber(string $branchId, string $preferred): string
    {
        $number = $preferred;

        for ($suffix = 1; $suffix <= 50; $suffix++) {
            $taken = DB::table('accounts')
                ->where('branch_id', $branchId)
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

    /** Names are unique per branch too. */
    private function availableName(string $branchId, string $preferred): string
    {
        $name = $preferred;

        for ($suffix = 2; $suffix <= 50; $suffix++) {
            $taken = DB::table('accounts')
                ->where('branch_id', $branchId)
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
