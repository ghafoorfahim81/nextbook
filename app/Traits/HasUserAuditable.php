<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait HasUserAuditable
{
    public static function bootHasUserAuditable()
    {
        $resolveUser = static fn () => Auth::user()
            ?? User::withoutGlobalScopes()->where('email', 'admin@nextbook.com')->first();

        static::creating(function ($model) use ($resolveUser) {
            $user = $resolveUser();
            if ($user?->id && empty($model->created_by)) {
                $model->created_by = $user->id;
            }
        });

        static::updating(function ($model) use ($resolveUser) {
            $user = $resolveUser();
            if ($user?->id) {
                $model->updated_by = $user->id;
            }
        });

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            static::deleting(function ($model) use ($resolveUser) {
                $user = $resolveUser();
                if ($user?->id) {
                    $model->deleted_by = $user->id;
                    $model->save();
                }
            });
            
            static::restored(function ($model) use ($resolveUser) {
                // Get the current user or fallback to admin
                $user = $resolveUser();
                
                // Set deleted_by to null and updated_by to the restoring user
                $model->deleted_by = null;
                if ($user?->id) {
                    $model->updated_by = $user->id;
                }
                
                // Save without firing events to avoid infinite loop
                $model->saveQuietly();
            });
        }
    }
}