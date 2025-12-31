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
        $user = Auth::user()??$adminUser;
        static::creating(function ($model) use ($user) {
            $model->created_by =  $user->id ?? null;
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
