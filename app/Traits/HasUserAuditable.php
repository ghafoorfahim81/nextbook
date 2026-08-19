<?php

namespace App\Traits;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

trait HasUserAuditable
{
    /** @var array<string, array<int, string>> Column names per table, resolved once. */
    private static array $auditColumnCache = [];

    /**
     * Does this model's table actually carry the given audit column?
     *
     * Deliberately NOT isFillable(): $fillable governs mass assignment, and an
     * audit column is set by the framework, not by a request — only 13 of the
     * 70 soft-deletable models list deleted_by there. Gating on fillable meant
     * the other 57 recorded no one as having deleted the row, and the deleted
     * records screen attributed every one of them to "System".
     */
    protected static function auditColumnExists($model, string $column): bool
    {
        $table = $model->getTable();

        if (! array_key_exists($table, self::$auditColumnCache)) {
            self::$auditColumnCache[$table] = $model->getConnection()
                ->getSchemaBuilder()
                ->getColumnListing($table);
        }

        return in_array($column, self::$auditColumnCache[$table], true);
    }

    public static function bootHasUserAuditable()
    {
        $resolveUser = static fn () => Auth::user()
            ?? User::withoutGlobalScopes()->where('email', 'admin@nextbook.af')->first();

        static::creating(function ($model) use ($resolveUser) {
            $user = $resolveUser();
            if ($user?->id && empty($model->created_by)) {
                $model->created_by = $user->id;
            }

            // Keep updated_by reserved for true edits only.
            if (method_exists($model, 'isFillable') && $model->isFillable('updated_by')) {
                $model->updated_by = null;
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
                    if (static::auditColumnExists($model, 'deleted_by_id')) {
                        $model->deleted_by_id = $user->id;
                    } elseif (static::auditColumnExists($model, 'deleted_by')) {
                        $model->deleted_by = $user->id;
                    }
                    $model->save();
                }
            });
            
            static::restored(function ($model) use ($resolveUser) {
                // Get the current user or fallback to admin
                $user = $resolveUser();
                
                // Set deleted_by to null and updated_by to the restoring user
                if (static::auditColumnExists($model, 'deleted_by_id')) {
                    $model->deleted_by_id = null;
                }
                if (static::auditColumnExists($model, 'deleted_by')) {
                    $model->deleted_by = null;
                }
                if ($user?->id) {
                    $model->updated_by = $user->id;
                }
                
                // Save without firing events to avoid infinite loop
                $model->saveQuietly();
            });
        }
    }
}
