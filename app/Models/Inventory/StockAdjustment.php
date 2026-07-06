<?php

namespace App\Models\Inventory;

use App\Enums\StockAdjustmentReason;
use App\Enums\StockMovementType;
use App\Models\Transaction\Transaction;
use App\Traits\BranchSpecific;
use App\Traits\HasAttachments;
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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class StockAdjustment extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, HasAttachments, SoftDeletes;

    protected $fillable = [
        'reference',
        'date',
        'type',
        'reason',
        'warehouse_id',
        'status',
        'branch_id',
        'notes',
        'fiscal_period_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'type' => StockMovementType::class,
            'reason' => StockAdjustmentReason::class,
            'warehouse_id' => 'string',
            'branch_id' => 'string',
            'fiscal_period_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'reference',
            'date',
            'type',
            'reason',
            'status',
            'notes',
        ];
    }

    protected array $allowedFilters = [
        'warehouse_id',
        'type',
        'reason',
        'status',
        'items.item_id',
        'date',
        'created_by',
    ];

    public function items(): HasMany
    {
        return $this->hasMany(StockAdjustmentItem::class);
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class, 'branch_id');
    }

    public function fiscalPeriod(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Accounting\FinancialPeriod::class, 'fiscal_period_id');
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }

    public function getDependencyMessage(): string
    {
        return 'You cannot delete this stock adjustment because it has dependencies.';
    }
}
