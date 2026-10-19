<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Role as SpatieRole;
use Symfony\Component\Uid\Ulid;

class Role extends SpatieRole
{
    // The roles table carries a `deleted_at` column and the restore route
    // queries `withTrashed()`, but without this trait `delete()` wiped the row
    // (cascading its permission and user pivots) and restore threw.
    use SoftDeletes;

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

    public function users(): BelongsToMany
    {
        return $this->morphedByMany(
            User::class,
            'model',
            config('permission.table_names.model_has_roles'),
            app(PermissionRegistrar::class)->pivotRole,
            config('permission.column_names.model_morph_key')
        );
    }
}

