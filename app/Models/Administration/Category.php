<?php

namespace App\Models\Administration;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasCache;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;
use App\Traits\HasBranch;
use App\Traits\BranchSpecific;
class Category extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasCache, HasSearch, HasSorting, HasDependencyCheck, BranchSpecific, HasBranch, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'categories';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'parent_id',
        'remark',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'parent_id' => 'string',
        'is_active' => 'boolean',
        'created_by' => 'integer',
        'updated_by' => 'integer',
    ];


    /**
     * Columns used for searchable queries.
     *
     * @return array
     */
    protected static function searchableColumns(): array
    {
        return [
            'name',
            'parent.name',
        ];
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This category is used in items'
            ],
            'children' => [
                'model' => 'subcategories',
                'message' => 'This category has subcategories'
            ]
        ];
    }

    /**
     * Check if category is deleted
     */
    public function isDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }

    /**
     * Relationship to items that use this category
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'category_id');
    }
}
