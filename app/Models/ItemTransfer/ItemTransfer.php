<?php

namespace App\Models\ItemTransfer;

use App\Enums\TransactionStatus;
use App\Traits\CompanySpecific;
use App\Traits\HasAttachments;
use App\Traits\HasBranch;
use App\Traits\HasCompany;
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
    use HasUlids, HasSearch, HasSorting, HasDynamicFilters, HasUserAuditable, HasUserTracking, CompanySpecific, HasCompany, HasBranch, HasDependencyCheck, HasAttachments, SoftDeletes;

    protected $table = 'item_transfers';

    protected $fillable = [
        'company_id',
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
        // Phase 4: the receiving branch, and when it took the goods in.
        'to_branch_id',
        'received_at',
        'received_by',
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
            'received_at' => 'datetime',
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

    // Both ends show on either branch's copy of the transfer, so the other
    // branch's warehouse must not be filtered out by its branch scope.
    public function fromWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'from_warehouse_id')
            ->withoutGlobalScope('branchSpecific');
    }

    public function toWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'to_warehouse_id')
            ->withoutGlobalScope('branchSpecific');
    }

    /**
     * A transfer belongs to two branches once it crosses them (phase 4): the
     * one sending and the one receiving, which has to see it to take the goods
     * in. So instead of BranchSpecific's branch_id-only scope, under the same
     * name so `withoutGlobalScope('branchSpecific')` keeps working. Like
     * BranchSpecific, no branch (a job, a command) means no filter.
     */
    protected static function booted(): void
    {
        static::addGlobalScope('branchSpecific', function (\Illuminate\Database\Eloquent\Builder $builder) {
            $branchId = \App\Support\CompanyContext::branchId();

            if ($branchId === null) {
                return;
            }

            $table = $builder->getModel()->getTable();
            $builder->where(fn ($query) => $query
                ->where("{$table}.branch_id", $branchId)
                ->orWhere("{$table}.to_branch_id", $branchId));
        });
    }

    public function toBranch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class, 'to_branch_id');
    }

    /** Whether the goods change branch, which makes it a send-and-receive (phase 4). */
    public function isInterbranch(): bool
    {
        return $this->to_branch_id !== null
            && (string) $this->to_branch_id !== (string) $this->branch_id;
    }

    /** Sent to another branch and not yet received there (phase 4). */
    public function isInTransit(): bool
    {
        return $this->isInterbranch()
            && $this->status === TransactionStatus::POSTED
            && $this->received_at === null;
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
        return __('messages.dependency.cannot_delete_document', ['document' => __('messages.dependency.documents.item_transfer')]);
    }
}
