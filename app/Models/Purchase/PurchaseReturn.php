<?php

namespace App\Models\Purchase;

use App\Enums\PurchaseReturnReason;
use App\Enums\TransactionStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Models\Transaction\Transaction;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;

class PurchaseReturn extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasDynamicFilters, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'number',
        'purchase_id',
        'supplier_id',
        'date',
        'reason',
        'description',
        'status',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_id' => 'string',
            'supplier_id' => 'string',
            'date' => 'date',
            'reason' => PurchaseReturnReason::class,
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'number',
            'date',
            'reason',
            'description',
            'status',
        ];
    }

    protected array $allowedFilters = [
        'purchase_id',
        'supplier_id',
        'reason',
        'status',
        'date',
        'created_by',
    ];

    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Ledger\Ledger::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseReturnItem::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class, 'branch_id');
    }

    public function getDependencyMessage(): string
    {
        return 'You cannot delete this purchase return because it has dependencies.';
    }

    public function returnTotal(): float
    {
        return (float) $this->items->sum(fn ($item) => (float) $item->quantity * (float) $item->unit_price);
    }

    public function isDraft(): bool
    {
        return $this->status === TransactionStatus::DRAFT->value;
    }

    public function isPosted(): bool
    {
        return $this->status === TransactionStatus::POSTED->value;
    }
}
