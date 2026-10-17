<?php

namespace App\Services;

use App\Models\Administration\Branch;
use App\Models\Administration\Warehouse;
use App\Support\BranchContext;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Uid\Ulid;

/**
 * Creates the default data a branch needs in order to trade.
 *
 * Almost everything a branch used to be given — chart of accounts, currencies,
 * catalogue, customers, HR configuration — is company-level now and lives in
 * CompanyProvisioningService. Branches of one company share those, which is
 * what lets stock move between them as a transfer rather than a sale. What
 * remains genuinely per-branch is the warehouse.
 *
 * Provisioning a branch still provisions its company first. The company sets
 * have to exist before the branch can trade, both calls are idempotent, and
 * leaving the ordering to each caller is how a branch ends up half set up.
 *
 * Like its company counterpart this is atomic and idempotent: every set is
 * matched on its natural key, only what is missing gets written, and a failure
 * part-way through writes nothing.
 */
class BranchProvisioningService
{
    public function __construct(
        private readonly CompanyProvisioningService $companyProvisioning,
    ) {
    }

    /**
     * Provision (or backfill) the company's defaults and this branch's own.
     *
     * @return array<string, int> rows created per module
     */
    public function provision(Branch $branch, ?string $actorId = null): array
    {
        $actorId = $actorId ?? Auth::id() ?? $this->fallbackActorId();

        $company = $branch->company()->withoutGlobalScopes()->firstOrFail();

        $counts = $this->companyProvisioning->provision($company, $actorId);

        $counts['warehouses'] = DB::transaction(
            fn (): int => $this->warehouse($branch, $actorId)
        );

        BranchContext::flush($branch->id);

        return $counts;
    }

    private function warehouse(Branch $branch, ?string $actorId): int
    {
        $exists = Warehouse::withoutGlobalScopes()
            ->where('branch_id', $branch->id)
            ->exists();

        if ($exists) {
            return 0;
        }

        // Named from translations rather than copied off whatever main warehouse
        // happened to exist in another branch.
        Warehouse::withoutEvents(function () use ($branch, $actorId): void {
            (new Warehouse())->forceFill([
                'id' => (string) new Ulid(),
                'name' => __('general.main_warehouse'),
                'address' => __('general.main_warehouse'),
                'branch_id' => $branch->id,
                'is_main' => true,
                'created_by' => $actorId,
            ])->save();
        });

        return 1;
    }

    /**
     * An actor to attribute the rows to when provisioning runs outside a
     * request — a seeder or a console command has no authenticated user.
     */
    private function fallbackActorId(): ?string
    {
        return \App\Models\User::withoutGlobalScopes()->value('id');
    }
}
