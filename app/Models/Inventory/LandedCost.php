<?php

namespace App\Models\Inventory;

use App\Enums\LandedCostAllocationMethod;
use App\Enums\LandedCostStatus;
use App\Models\Purchase\Purchase;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandedCost extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable, HasDynamicFilters, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $table = 'landed_costs';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'date',
        'purchase_id',
        'total_cost',
        'allocated_total',
        'allocation_method',
        'status',
        'notes',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by_id',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'purchase_id' => 'string',
            'total_cost' => 'decimal:2',
            'allocated_total' => 'decimal:2',
            'allocation_method' => LandedCostAllocationMethod::class,
            'status' => LandedCostStatus::class,
            'notes' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'deleted_by_id' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'notes',
            'status',
            'allocation_method',
            'purchase.number',
            'purchases.number',
        ];
    }

    protected array $allowedFilters = [
        'purchase_id',
        'date',
        'status',
        'allocation_method',
        'created_by',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function purchases(): BelongsToMany
    {
        return $this->belongsToMany(Purchase::class, 'landed_cost_purchases')
            ->withTimestamps()
            ->withPivot('id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LandedCostItem::class, 'landed_cost_id');
    }

    protected function dynamicFilterHandlers(): array
    {
        return [
            'purchase_id' => function ($query, $value): void {
                $ids = is_array($value) ? array_values(array_filter($value)) : [$value];

                $query->whereHas('purchases', function ($relation) use ($ids): void {
                    $relation->whereIn('purchases.id', $ids);
                });
            },
        ];
    }

    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'landed cost items',
                'message' => 'This landed cost has allocation lines',
            ],
            'purchases' => [
                'model' => 'purchases',
                'message' => 'This landed cost is linked to purchases',
            ],
        ];
    }
}
