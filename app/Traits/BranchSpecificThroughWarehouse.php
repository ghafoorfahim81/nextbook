<?php

namespace App\Traits;

use App\Models\Administration\Warehouse;
use Illuminate\Database\Eloquent\Builder;

/**
 * BranchSpecific for stock, which carries no branch of its own.
 *
 * A stock row's branch is its warehouse's, so the row is matched through the
 * warehouse instead of a copied column that nothing would keep in step when a
 * warehouse changes hands. The scope keeps BranchSpecific's name, so
 * `withoutGlobalScope('branchSpecific')` reads the same on either kind of
 * model, and the same "no branch, no filter" rule outside a request.
 *
 * Deleted warehouses are not excluded: their stock was counted against the
 * branch before, and still is.
 */
trait BranchSpecificThroughWarehouse
{
    public static function bootBranchSpecificThroughWarehouse()
    {
        static::addGlobalScope('branchSpecific', function (Builder $builder) {
            $branchId = static::activeBranchIdForStock();

            if ($branchId === null) {
                return;
            }

            $builder->whereIn(
                $builder->getModel()->getTable().'.warehouse_id',
                Warehouse::idsOfBranch($branchId)
            );
        });
    }

    /**
     * The branch the current request is acting on, resolved exactly as
     * BranchSpecific resolves it.
     */
    protected static function activeBranchIdForStock(): ?string
    {
        if (app()->bound('active_branch_id')) {
            $branchId = app('active_branch_id');

            if ($branchId) {
                return (string) $branchId;
            }
        }

        $branchId = auth()->user()?->branch_id;

        return $branchId ? (string) $branchId : null;
    }
}
