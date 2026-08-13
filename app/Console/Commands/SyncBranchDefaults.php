<?php

namespace App\Console\Commands;

use App\Models\Administration\Branch;
use App\Services\BranchProvisioningService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class SyncBranchDefaults extends Command
{
    protected $signature = 'branches:sync-defaults
                            {branch? : Branch id to sync — omit to sync every branch}
                            {--dry-run : Report what is missing without writing anything}';

    protected $description = 'Create any default records a branch is missing (accounts, currencies, units, journal classes, …)';

    public function handle(BranchProvisioningService $provisioning): int
    {
        $branches = Branch::query()
            ->when($this->argument('branch'), fn ($query, $id) => $query->where('id', $id))
            ->orderBy('created_at')
            ->get();

        if ($branches->isEmpty()) {
            $this->components->error('No branch matched.');

            return self::FAILURE;
        }

        $dryRun = (bool) $this->option('dry-run');

        foreach ($branches as $branch) {
            $counts = $dryRun
                ? $this->dryRun($provisioning, $branch)
                : $provisioning->provision($branch);

            $missing = array_filter($counts);

            if ($missing === []) {
                $this->components->twoColumnDetail($branch->name, '<fg=gray>up to date</>');

                continue;
            }

            $summary = collect($missing)
                ->map(fn (int $count, string $module) => "{$module} +{$count}")
                ->implode(', ');

            $this->components->twoColumnDetail(
                $branch->name,
                $dryRun ? "<fg=yellow>would add</> {$summary}" : "<fg=green>added</> {$summary}"
            );
        }

        return self::SUCCESS;
    }

    /**
     * Provision inside a transaction that is always rolled back, so the counts are
     * real without anything being written.
     *
     * @return array<string, int>
     */
    private function dryRun(BranchProvisioningService $provisioning, Branch $branch): array
    {
        DB::beginTransaction();

        try {
            return $provisioning->provision($branch);
        } finally {
            DB::rollBack();
        }
    }
}
