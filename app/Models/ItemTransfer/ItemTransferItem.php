<?php

namespace App\Models\ItemTransfer;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ItemTransferItem extends Model
{
    use HasUlids, HasSearch, HasSorting, HasUserAuditable, BranchSpecific, HasDependencyCheck, SoftDeletes, HasBranch;

    protected $table = 'item_transfer_items';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'item_transfer_id',
        'item_id',
        'batch',
        'expire_date',
        'quantity',
        'measure_id',
        'unit_price',
        'branch_id',
    ];

    protected function casts(): array
    {
        return [
            'item_transfer_id' => 'string',
            'item_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'quantity' => 'decimal:4',
            'measure_id' => 'string',
            'unit_price' => 'decimal:4',
            'branch_id' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'batch',
            'expire_date',
            'quantity',
            'unit_price',
            'item.name',
            'item.code',
            'unitMeasure.name',
        ];
    }

    public function itemTransfer(): BelongsTo
    {
        return $this->belongsTo(ItemTransfer::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Item::class);
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class, 'measure_id');
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class);
    }

    public function deletedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'deleted_by');
    }
}
