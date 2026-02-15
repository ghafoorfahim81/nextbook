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
use App\Traits\BranchSpecific;
class Size extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, HasCache, BranchSpecific, HasBranch, HasSearch, HasSorting, HasDependencyCheck, SoftDeletes;

    protected $table = 'sizes';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'code',
        'is_active',
        'is_main',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
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

    public static function defaultSizes(): array
    {
        return [
            ['name' => 'خورد', 'code' => 'SM'],
            ['name' => 'متوسط', 'code' => 'MD'],
            ['name' => 'کلان', 'code' => 'LG'],
            ['name' => 'Small', 'code' => 'S'],
            ['name' => 'Medium', 'code' => 'M'],
            ['name' => 'Large', 'code' => 'L'],
            ['name' => 'X-Large', 'code' => 'XL'],
            ['name' => 'XL', 'code' => 'X1'],
            ['name' => 'XS', 'code' => 'XS'],
            ['name' => 'M', 'code' => 'M1'],
            ['name' => 'L', 'code' => 'L1'],
            ['name' => 'XXL', 'code' => 'XXL'],
            ['name' => 'A6', 'code' => 'A6'],
            ['name' => 'A5', 'code' => 'A5'],
            ['name' => 'A4', 'code' => 'A4'],
            ['name' => 'A3', 'code' => 'A3'],
            ['name' => 'A2', 'code' => 'A2'],
            ['name' => 'N_A', 'code' => 'NA']
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
