<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Settlement resolves its posting accounts by SLUG, never by name — the names
 * are localised into Dari and Pashto and a name lookup breaks as soon as a user
 * switches language. This brings existing branches in line with the slugs the
 * code now expects.
 *
 * 1. `foreign-exchange-gain` / `foreign-exchange-loss` become `fx-gain` /
 *    `fx-loss`. The accounts themselves are untouched; only the handle changes,
 *    so posted history keeps pointing at the same rows.
 * 2. Customer Advances and Supplier Advances are created where missing, so an
 *    overpayment has somewhere to land instead of being rejected.
 *
 * Idempotent, and it never creates a second account where one already answers
 * to the target slug — a duplicated FX account splits the P&L in half silently.
 */
return new class extends Migration
{
    private const SLUG_RENAMES = [
        'foreign-exchange-gain' => 'fx-gain',
        'foreign-exchange-loss' => 'fx-loss',
    ];

    private const ADVANCE_ACCOUNTS = [
        [
            'slug' => 'customer-advances',
            'name' => 'Customer Advances',
            'local_name' => 'پیش‌پرداخت مشتری',
            'number' => '5085',
            'account_type_slug' => 'other-current-liability',
            'parent_slug' => 'current-liabilities',
            'remark' => 'Unapplied money received from customers',
        ],
        [
            'slug' => 'supplier-advances',
            'name' => 'Supplier Advances',
            'local_name' => 'پیش‌پرداخت به تهیه‌کننده',
            'number' => '4075',
            'account_type_slug' => 'other-current-asset',
            'parent_slug' => 'advances-prepaid-deposit',
            'remark' => 'Money paid to suppliers ahead of a bill',
        ],
    ];

    public function up(): void
    {
        DB::transaction(function () {
            foreach (DB::table('branches')->pluck('id') as $branchId) {
                $this->renameFxSlugs((string) $branchId);
                $this->createAdvanceAccounts((string) $branchId);
            }
        });
    }

    private function renameFxSlugs(string $branchId): void
    {
        foreach (self::SLUG_RENAMES as $from => $to) {
            $legacy = DB::table('accounts')
                ->where('branch_id', $branchId)
                ->where('slug', $from)
                ->whereNull('deleted_at')
                ->first(['id', 'name']);

            if (! $legacy) {
                continue;
            }

            $taken = DB::table('accounts')
                ->where('branch_id', $branchId)
                ->where('slug', $to)
                ->whereNull('deleted_at')
                ->exists();

            if ($taken) {
                // Both slugs present. Renaming would collide on the unique
                // index, and picking a winner is a judgement call about which
                // account history was posted to. Report and leave both alone.
                Log::warning('Skipped FX slug rename: both slugs already exist.', [
                    'branch_id' => $branchId,
                    'from' => $from,
                    'to' => $to,
                    'legacy_account_id' => $legacy->id,
                ]);

                continue;
            }

            DB::table('accounts')->where('id', $legacy->id)->update([
                'slug' => $to,
                'updated_at' => now(),
            ]);
        }
    }

    private function createAdvanceAccounts(string $branchId): void
    {
        // accounts.created_by is NOT NULL with a foreign key, so provisioning
        // needs a real user to attribute the row to.
        $actorId = DB::table('users')->orderBy('created_at')->value('id');

        if (! $actorId) {
            return;
        }

        foreach (self::ADVANCE_ACCOUNTS as $definition) {
            $exists = DB::table('accounts')
                ->where('branch_id', $branchId)
                ->where('slug', $definition['slug'])
                ->whereNull('deleted_at')
                ->exists();

            if ($exists) {
                continue;
            }

            $typeId = DB::table('account_types')
                ->where('branch_id', $branchId)
                ->where('slug', $definition['account_type_slug'])
                ->whereNull('deleted_at')
                ->value('id');

            if (! $typeId) {
                Log::warning('Skipped advance account: branch has no matching account type.', [
                    'branch_id' => $branchId,
                    'slug' => $definition['slug'],
                    'account_type_slug' => $definition['account_type_slug'],
                ]);

                continue;
            }

            $number = $this->availableNumber($branchId, $definition['number']);
            $name = $this->availableName($branchId, $definition['name']);

            DB::table('accounts')->insert([
                'id' => (string) Str::ulid(),
                'name' => $name,
                'local_name' => $definition['local_name'],
                'number' => $number,
                'account_type_id' => $typeId,
                'parent_id' => DB::table('accounts')
                    ->where('branch_id', $branchId)
                    ->where('slug', $definition['parent_slug'])
                    ->whereNull('deleted_at')
                    ->value('id'),
                'is_active' => true,
                'is_main' => true,
                'slug' => $definition['slug'],
                'branch_id' => $branchId,
                'remark' => $definition['remark'],
                'created_by' => $actorId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
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
        DB::transaction(function () {
            foreach (array_flip(self::SLUG_RENAMES) as $from => $to) {
                DB::table('accounts')->where('slug', $from)->update(['slug' => $to]);
            }

            // The advance accounts are left in place. Dropping them would
            // orphan any settlement that posted an overpayment to them.
        });
    }
};
