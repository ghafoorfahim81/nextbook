<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BranchSpecific
{
    public static function bootBranchSpecific()
    {
        static::addGlobalScope('branchSpecific', function (Builder $builder) {
            $branchId = static::activeBranchId();

            // Outside a web request (console command, queue worker, unauthenticated
            // request) there is no branch to scope to. Leave the query alone instead of
            // filtering on a null branch — that matches no rows and makes every model
            // look empty, silently, in every scheduled job and artisan command.
            if ($branchId === null) {
                return;
            }

            $builder->where(static::getBranchColumn(), $branchId);
        });
    }

    /**
     * The branch the current request is acting on, or null when there is none.
     */
    protected static function activeBranchId(): ?string
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

    protected static function getBranchColumn()
    {
        return (new self)->getTable().'.branch_id';
    }
}
