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
        $firstUser = \App\Models\User::where('email', 'admin@nextbook.com')->first();
        $branch = \App\Models\Administration\Branch::first();
        if (!$firstUser) {
            $firstUser = User::create([
                'id' => (string) new Ulid(),
                'name' => 'admin',
                'email' => 'admin@nextbook.com',
                'branch_id' => $branch?->id,
                'password' => bcrypt('password'),
                'preferences' => User::DEFAULT_PREFERENCES,
            ]);
        }
        static::creating(function ($model) use ($firstUser, $branch) {
            $model->created_by =  $firstUser->id;
        });

        static::updating(function ($model) use ($firstUser) {
            $model->updated_by =  $firstUser->id;
        });
        if (in_array(\Illuminate\Database\Eloquent\SoftDeletes::class, class_uses_recursive(static::class))) {
            static::deleting(function ($model) use ($firstUser) {
                $model->deleted_by = $firstUser->id;
                $model->save();
            });
            static::restoring(function ($model) {
                $model->deleted_by = null;
            });
        }
    }
}
