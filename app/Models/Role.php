<?php

namespace App\Models;

use Spatie\Permission\Models\Role as SpatieRole;
use Symfony\Component\Uid\Ulid;
use App\Traits\BranchSpecific;
class Role extends SpatieRole
{ 
    protected $keyType = 'string';
    public $incrementing = false;

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) new Ulid();
            }
        });
    }
}

