<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

trait BelongsToBranch
{
    /**
     * Boot the branch global scope and automatic assignment.
     */
    protected static function bootBelongsToBranch(): void
    {
        // Global scope: always constrain by the active branch when available.
        static::addGlobalScope('branch', function (Builder $builder) {
            // Resolve the active branch from the container, which is
            // set by the SetActiveBranch middleware for every request.
            if (!app()->bound('active_branch_id')) {
                return;
            }

            $branchId = app('active_branch_id');

            if ($branchId) {
                $builder->where($builder->getModel()->getTable() . '.branch_id', $branchId);
            }
        });

        // Automatically assign branch_id on create if not set explicitly.
        static::creating(function (Model $model) {
            if (!app()->bound('active_branch_id')) {
                return;
            }

            if (empty($model->branch_id)) {
                $model->branch_id = app('active_branch_id');
            }
        });
    }

    /**
     * Allow querying across all branches.
     *
     * Intended for super-admin usage together with authorization checks.
     */
    public function scopeAllBranches(Builder $query): Builder
    {
        return $query->withoutGlobalScope('branch');
    }
}


