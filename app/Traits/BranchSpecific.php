<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;

trait BranchSpecific
{
    public static function bootBranchSpecific()
    {
        static::addGlobalScope('branchSpecific', function (Builder $builder) {
            $builder->where(static::getBranchColumn(), auth()->user()?->branch_id);
        });
    }

    protected static function getBranchColumn()
    {
        return (new self)->getTable().'.branch_id';
    }
}
