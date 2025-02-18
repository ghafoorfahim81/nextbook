<?php

namespace App\Traits;

// use Auth;
use Illuminate\Support\Facades\Auth;

trait HasUserAuditable
{
    public static function bootHasUserAuditable()
    {
        $firstUser = \App\Models\User::first();
        static::creating(function ($model) use($firstUser) {
            $model->created_by = Auth::id() ?? $firstUser;
        });

        static::updating(function ($model) use($firstUser) {
            $model->updated_by = Auth::id() ?? $firstUser;
        });

        static::deleting(function ($model) use($firstUser) {
            $model->deleted_by = Auth::id() ?? $firstUser;
            $model->save();
        });

//        static::restoring(function ($model) {
//            $model->deleted_by = null;
//        });
    }
}
