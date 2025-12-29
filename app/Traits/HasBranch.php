<?php

namespace App\Traits;

trait HasBranch
{
    public static function bootHasBranch()
    {
        static::creating(function ($model) {
            $model->branch_id = auth()->user()->branch_id;
        });
    }
}
