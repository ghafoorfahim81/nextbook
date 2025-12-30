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
class Brand extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, HasCache, BranchSpecific, HasBranch, HasSearch, HasSorting, HasDependencyCheck, SoftDeletes;

    protected $table = 'brands';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'legal_name',
        'registration_number',
        'logo',
        'email',
        'phone',
        'website',
        'industry',
        'type',
        'address',
        'city',
        'country',
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
        return ['name', 'legal_name', 'registration_number', 'email', 'phone'];
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This brand is used in items'
            ]
        ];
    }

    /**
     * Relationship to items that use this brand
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'brand_id');
    }
}
