<?php

namespace App\Models\Inventory;

use App\Models\Administration\LandedCostCategory;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandedCostCategoryAllocation extends Model
{
    use HasFactory, HasUlids, HasUserTracking, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $table = 'landed_cost_category_allocations';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'landed_cost_id',
        'landed_cost_category_id',
        'amount',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'landed_cost_id' => 'string',
            'landed_cost_category_id' => 'string',
            'amount' => 'decimal:2',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'deleted_by' => 'string',
        ];
    }

    public function landedCost(): BelongsTo
    {
        return $this->belongsTo(LandedCost::class, 'landed_cost_id');
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(LandedCostCategory::class, 'landed_cost_category_id');
    }
}
