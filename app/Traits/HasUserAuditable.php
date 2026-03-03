<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\Uid\Ulid;

trait HasUserAuditable
{
    public static function bootHasUserAuditable()
    {
        $adminUser = \App\Models\User::withoutGlobalScopes()->where('email', 'admin@nextbook.com')->first();
        if (!$adminUser) {
            $adminUser = User::create([
                'id' => (string) new Ulid(),
                'name' => 'admin',
                'email' => 'admin@nextbook.com',
                'password' => bcrypt('password'),
                'preferences' => User::DEFAULT_PREFERENCES,
            ]);
        }

        static::creating(function ($model) use ($adminUser) {
            $user = Auth::user() ?? $adminUser;
            $model->created_by = $user->id ?? null;
        });

        static::updating(function ($model) use ($adminUser) {
            $user = Auth::user() ?? $adminUser;
            $model->updated_by = $user->id;
        });

        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            static::deleting(function ($model) use ($adminUser) {
                $user = Auth::user() ?? $adminUser;
                $model->deleted_by = $user->id;
                $model->save();
            });
            
            static::restored(function ($model) use ($adminUser) {
                // Get the current user or fallback to admin
                $user = Auth::user() ?? $adminUser;
                
                // Set deleted_by to null and updated_by to the restoring user
                $model->deleted_by = null;
                $model->updated_by = $user->id;
                
                // Save without firing events to avoid infinite loop
                $model->saveQuietly();
            });
        }
    }
}