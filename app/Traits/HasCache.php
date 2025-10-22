<?php

namespace App\Traits;

// use Auth;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\Ulid;

trait HasCache
{
    public static function bootHasCache()
    {
        static::created(function ($model) {
            Cache::forget($model->getTable());
        });
        static::updated(function ($model) {
            Cache::forget($model->getTable());
        });
        static::deleted(function ($model) {
            Cache::forget($model->getTable());
        });
        static::restored(function ($model) {
            Cache::forget($model->getTable());
        });
    }
}
