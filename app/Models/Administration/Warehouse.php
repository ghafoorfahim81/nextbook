<?php

namespace App\Models\Administration;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasCache;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Warehouse extends Model
{
    use HasFactory;
    use HasUserAuditable;
    use HasUserTracking;
    use HasUlids;
    use HasCache;
    use HasSearch;
    use HasSorting;
    use BranchSpecific;
    use HasBranch;
    use SoftDeletes;
    use HasDependencyCheck;

    protected $table = 'warehouses';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'address',
        'is_main',
        'is_active',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'is_main' => 'boolean',
        'is_active' => 'boolean',
        'branch_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'parent_id' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'address',
            'is_main',
            'branch.name',
        ];
    }

    protected function getRelationships(): array
    {
        return [
            'stocks' => [
                'model' => 'stocks',
                'message' => 'This warehouse is associated with stocks',
            ],
        ];
    }

    public function stocks()
    {
        return $this->hasMany(\App\Models\Inventory\StockMovement::class, 'warehouse_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public static function main()
    {
        return self::where('is_main', true)->first();
    }
}

