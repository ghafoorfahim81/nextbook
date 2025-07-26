<?php

namespace App\Traits;

// use Auth;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\Ulid;

trait HasBranch
{
    public static function bootHasBranch()
    {
        $branch = \App\Models\Administration\Branch::first();
        static::creating(function ($model) use($branch) {
            $model->branch_id =  $branch->id;
        });

    }
}
