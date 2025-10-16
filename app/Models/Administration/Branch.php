<?php

namespace App\Models\Administration;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class Branch extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting, HasDependencyCheck, SoftDeletes;

    protected $table = 'branches';
    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing



    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_main',
        'parent_id',
        'location',
        'sub_domain',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'location',
            'sub_domain',
            'remark',
            'parent.name'
        ];
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_main' => 'boolean',
        'parent_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    public function children()
    {
        return $this->hasMany(Branch::class, 'parent_id');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This branch is used in items'
            ],
            'accounts' => [
                'model' => 'accounts',
                'message' => 'This branch is used in accounts'
            ],
            'children' => [
                'model' => 'subbranches',
                'message' => 'This branch has subbranches'
            ]
        ];
    }

    /**
     * Relationship to items that belong to this branch
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'branch_id');
    }

    /**
     * Relationship to accounts that belong to this branch
     */
    public function accounts()
    {
        return $this->hasMany(\App\Models\Account\Account::class, 'branch_id');
    }
}
