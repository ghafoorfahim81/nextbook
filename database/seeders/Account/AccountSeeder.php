<?php

namespace Database\Seeders\Account;

use App\Models\Account\Account;
use App\Models\Account\AccountType;
use App\Models\Administration\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Symfony\Component\Uid\Ulid;
class AccountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Get the main branch
        $mainBranch = Branch::where('is_main', true)->first();

        $accounts = Account::defaultAccounts();
        $createdBy = User::where('name', 'admin')->first()->id;

        // Maps slug => id for accounts created in this run, so entries that
        // declare a `parent_slug` can be linked to their parent.
        $createdIds = [];

        foreach ($accounts as $account) {
            $created = Account::create([
                'id' => (string) new Ulid(),
                'name' => $account['name'],
                'local_name' => $account['local_name'],
                'number' => $account['number'],
                'account_type_id' => $account['account_type_id'],
                'parent_id' => isset($account['parent_slug'])
                    ? ($createdIds[$account['parent_slug']] ?? null)
                    : null,
                'slug' => $account['slug'],
                'branch_id' => $mainBranch?->id,
                'remark' => $account['remark'],
                'is_main' => $account['is_main'],
                'created_by' => $createdBy,
            ]);

            $createdIds[$account['slug']] = $created->id;
        }
    }
}
