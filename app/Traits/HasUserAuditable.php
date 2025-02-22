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
        if(!$firstUser){
            $firstUser = User::create([
                'id' => (string) new Ulid(),
                'name' => 'admin',
                'email' => 'admin@gmail.com',
                'password' => bcrypt('password'),
            ]);
        }
        static::creating(function ($model) use($firstUser) {
            $model->created_by = Auth::id() ?? $firstUser->id;
        });

        static::updating(function ($model) use($firstUser) {
            $model->updated_by = Auth::id() ?? $firstUser->id;
        });

        static::deleting(function ($model) use($firstUser) {
            $model->deleted_by = Auth::id() ?? $firstUser->id;
            $model->save();
        });

//        static::restoring(function ($model) {
//            $model->deleted_by = null;
//        });
    }
}
