<?php

namespace App\Models\ItemTransfer;

use App\Enums\TransferStatus;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemTransfer extends Model
{
    use HasUlids, HasSearch, HasSorting, HasUserAuditable, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $table = 'item_transfers';

    protected $fillable = [
        'date',
        'from_store_id',
        'to_store_id',
        'status',
        'transfer_cost',
        'branch_id',
        'remarks',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'from_store_id' => 'string',
            'to_store_id' => 'string',
            'status' => TransferStatus::class,
            'transfer_cost' => 'decimal:4',
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
            'fromStore.name',
            'toStore.name',
        ];
    }

    public function fromStore(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Store::class, 'from_store_id');
    }

    public function toStore(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Store::class, 'to_store_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(ItemTransferItem::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'updated_by');
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }

    public function getDependencyMessage(): string
    {
        return 'You cannot delete this transfer because it has dependencies.';
    }
}
