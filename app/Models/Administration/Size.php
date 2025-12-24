<?php

namespace App\Models\Administration;

use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasCache;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class Size extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, HasCache, HasBranch, HasSearch, HasSorting, HasDependencyCheck, SoftDeletes;

    protected $table = 'sizes';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'code',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'branch_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return ['name', 'code'];
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This size is used in items'
            ]
        ];
    }

    /**
     * Relationship to items that use this size
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'size_id');
    }
}
