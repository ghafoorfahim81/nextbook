<?php

namespace App\Traits;

use App\Models\Administration\Branch;

trait HasBranch
{
    public static function bootHasBranch()
    {
        static::creating(function ($model) {
            $model->branch_id = auth()->user()->branch_id??Branch::first()->id;
        });
    }
}
