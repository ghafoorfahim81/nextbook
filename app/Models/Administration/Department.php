<?php

namespace App\Models\Administration;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasCache;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;
use App\Traits\BranchSpecific;
class Department extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, HasDependencyCheck, BranchSpecific, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new Ulid(); // Generate ULID
        });
    }

    protected $fillable = [
        'id',
        'name',
        'code',
        'remark',
        'parent_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'parent_id' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'code',
            'remark',
            'parent.name'
        ];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'parent_id');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'children' => [
                'model' => 'subdepartments',
                'message' => 'This department has subdepartments'
            ]
        ];
    }

    /**
     * Get the child departments
     */
    public function children()
    {
        return $this->hasMany(Department::class, 'parent_id');
    }
}
