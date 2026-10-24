<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Phase 4: a transfer to another branch is sent, then received.
 *
 * Between two warehouses of one branch nothing changes — the transfer posts
 * both legs at once. When the destination warehouse is in another branch the
 * transfer is posted when the goods leave (stock out of the source, value into
 * Goods in Transit) and received by the other branch later (stock in, out of
 * Goods in Transit), possibly short — the shortfall is booked as shrinkage.
 *
 * received_at is what tells the two states apart: a cross-branch transfer that
 * is posted and not yet received is in transit. Status keeps the vocabulary
 * every document shares.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('item_transfers', function (Blueprint $table) {
            $table->ulid('to_branch_id')->nullable()->after('branch_id');
            $table->timestamp('received_at')->nullable();
            $table->ulid('received_by')->nullable();

            $table->index(['company_id', 'to_branch_id']);
            // The receiving branch is one of the same company's.
            $table->foreign(['company_id', 'to_branch_id'])->references(['company_id', 'id'])->on('branches');
            $table->foreign('received_by')->references('id')->on('users')->nullOnDelete();
        });

        Schema::table('item_transfer_items', function (Blueprint $table) {
            $table->decimal('received_quantity', 19, 4)->nullable();
        });

        // Existing transfers: the destination's branch, and — having moved in
        // one step — already received in full.
        DB::statement('
            UPDATE item_transfers AS t SET to_branch_id = w.branch_id
            FROM warehouses AS w WHERE w.id = t.to_warehouse_id
        ');
        DB::statement("UPDATE item_transfers SET received_at = updated_at WHERE status = 'posted'");
        DB::statement("
            UPDATE item_transfer_items AS i SET received_quantity = i.quantity
            FROM item_transfers AS t WHERE t.id = i.item_transfer_id AND t.status = 'posted'
        ");

        // Every existing company gets the Goods in Transit account; new ones
        // get it from provisioning (Account::defaultAccounts).
        $companies = DB::table('companies AS c')
            ->join('account_types AS at', fn ($join) => $join->on('at.company_id', '=', 'c.id')
                ->where('at.slug', 'other-current-asset')->whereNull('at.deleted_at'))
            ->whereNull('c.deleted_at')
            ->whereNotExists(fn ($q) => $q->from('accounts AS a')
                ->whereColumn('a.company_id', 'c.id')->where('a.slug', 'goods-in-transit'))
            ->get(['c.id', 'c.created_by', 'at.id AS account_type_id']);

        foreach ($companies as $company) {
            $creator = $company->created_by
                ?? DB::table('users')->where('company_id', $company->id)->orderBy('created_at')->value('id');

            if (! $creator) {
                continue;
            }

            DB::table('accounts')->insert([
                'id' => (string) Str::ulid(),
                'name' => 'Goods in Transit',
                'local_name' => 'کالای در راه',
                'number' => '3025',
                'account_type_id' => $company->account_type_id,
                'is_active' => true,
                'is_main' => true,
                'slug' => 'goods-in-transit',
                'company_id' => $company->id,
                'remark' => 'Stock sent between branches and not yet received',
                'created_by' => $creator,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('accounts')->where('slug', 'goods-in-transit')
            ->whereNotExists(fn ($q) => $q->from('transaction_lines')->whereColumn('transaction_lines.account_id', 'accounts.id'))
            ->delete();

        Schema::table('item_transfer_items', fn (Blueprint $table) => $table->dropColumn('received_quantity'));

        Schema::table('item_transfers', function (Blueprint $table) {
            $table->dropForeign(['company_id', 'to_branch_id']);
            $table->dropForeign(['received_by']);
            $table->dropIndex(['company_id', 'to_branch_id']);
            $table->dropColumn(['to_branch_id', 'received_at', 'received_by']);
        });
    }
};
