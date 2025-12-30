<?php

namespace App\Traits;

use App\Models\Administration\Branch;

trait HasBranch
{
    public static function bootHasBranch()
    {
        static::creating(function ($model) {
            // Only set the branch_id if it's not already set
            if (empty($model->branch_id)) {
                $model->branch_id = auth()->user()->branch_id ?? Branch::first()->id;
            }
        });
    }
}
