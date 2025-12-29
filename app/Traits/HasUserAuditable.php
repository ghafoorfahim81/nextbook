<?php

namespace App\Traits;

// use Auth;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\Ulid;

trait HasUserAuditable
{
    public static function bootHasUserAuditable()
    {
        $user = Auth::user()??\App\Models\User::where('email', 'admin@nextbook.com')->first();
        static::creating(function ($model) use ($user) {
            $model->created_by =  $user->id;
        });

        static::updating(function ($model) use ($user) {
            $model->updated_by =  $user->id;
        });
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            static::deleting(function ($model) use ($user) {
                $model->deleted_by = $user->id;
                $model->save();
            });
            static::restoring(function ($model) {
                $model->deleted_by = null;
            });
        }
    }
}
