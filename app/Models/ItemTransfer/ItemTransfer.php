<?php

namespace App\Models\ItemTransfer;

use App\Enums\TransactionStatus;
use App\Traits\BranchSpecific;
use App\Traits\HasAttachments;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemTransfer extends Model
{
    use HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, HasDependencyCheck, HasAttachments, SoftDeletes;

    protected $table = 'item_transfers';

    protected $fillable = [
        'date',
        'from_warehouse_id',
        'to_warehouse_id',
        'status',
        'has_transfer_cost',
        'transfer_cost',
        'bank_account_id',
        'expense_account_id',
        'currency_id',
        'rate',
        'branch_id',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'from_warehouse_id' => 'string',
            'to_warehouse_id' => 'string',
            'status' => TransactionStatus::class,
            'has_transfer_cost' => 'boolean',
            'transfer_cost' => 'decimal:4',
            'bank_account_id' => 'string',
            'expense_account_id' => 'string',
            'currency_id' => 'string',
            'rate' => 'decimal:6',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'date',
            'status',
            'remarks',
            'fromWarehouse.name',
            'toWarehouse.name',
        ];
    }

    protected array $allowedFilters = [
        'from_warehouse_id',
        'to_warehouse_id',
        'items.item_id',
        'date',
        'created_by',
    ];

    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'from_warehouse_id');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'to_warehouse_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemTransferItem::class);
    }

    public function bankAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account\Account::class, 'bank_account_id');
    }

    public function expenseAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account\Account::class, 'expense_account_id');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Currency::class, 'currency_id');
    }



    public function getDependencyMessage(): string
    {
        return 'You cannot delete this transfer because it has dependencies.';
    }
}
