<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Phase 7: the Inter-branch Clearing account (3026), for every company that
 * exists already; new ones get it from provisioning (Account::defaultAccounts).
 *
 * A shared expense split across branches leaves the payment in the branch that
 * paid and puts each other branch's part in that branch. The clearing account
 * is what keeps both sides' books balanced: the paying branch is owed, the
 * carrying branch owes, and over the company the two cancel out.
 */
return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('companies AS c')
            ->join('account_types AS at', fn ($join) => $join->on('at.company_id', '=', 'c.id')
                ->where('at.slug', 'other-current-asset')->whereNull('at.deleted_at'))
            ->whereNull('c.deleted_at')
            ->whereNotExists(fn ($q) => $q->from('accounts AS a')
                ->whereColumn('a.company_id', 'c.id')->where('a.slug', 'inter-branch-clearing'))
            ->get(['c.id', 'c.created_by', 'at.id AS account_type_id']);

        foreach ($companies as $company) {
            $creator = $company->created_by
                ?? DB::table('users')->where('company_id', $company->id)->orderBy('created_at')->value('id');

            if (! $creator) {
                continue;
            }

            DB::table('accounts')->insert([
                'id' => (string) Str::ulid(),
                'name' => 'Inter-branch Clearing',
                'local_name' => 'حساب بین شعبات',
                'number' => '3026',
                'account_type_id' => $company->account_type_id,
                'is_active' => true,
                'is_main' => true,
                'slug' => 'inter-branch-clearing',
                'company_id' => $company->id,
                'remark' => 'Between branches of the company; nets to zero',
                'created_by' => $creator,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('slug', 'inter-branch-clearing')
            ->whereNotExists(fn ($q) => $q->from('transaction_lines')->whereColumn('transaction_lines.account_id', 'accounts.id'))
            ->delete();
    }
};
