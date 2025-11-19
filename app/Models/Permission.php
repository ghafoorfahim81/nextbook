<?php

namespace App\Models;

use Spatie\Permission\Models\Permission as SpatiePermission;
use Symfony\Component\Uid\Ulid;

class Permission extends SpatiePermission
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

