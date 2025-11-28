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
        $firstUser = \App\Models\User::first();
        $branch = \App\Models\Administration\Branch::first();
        if (!$firstUser) {
            $firstUser = User::create([
                'id' => (string) new Ulid(),
                'name' => 'admin_1',
                'email' => 'admin@nextbook1.com',
                'branch_id' => $branch?->id,
                'password' => bcrypt('password'),
            ]);
        }
        static::creating(function ($model) use ($firstUser, $branch) {
            $model->created_by =  $firstUser->id;
        });

        static::updating(function ($model) use ($firstUser) {
            $model->updated_by =  $firstUser->id;
        });

        // Only register soft delete events if the model uses SoftDeletes
        if (method_exists(static::class, 'bootSoftDeletes')) {
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
