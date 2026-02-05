<?php

namespace App\Models\AccountTransfer;

use App\Models\Transaction\Transaction;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\BranchSpecific;
class AccountTransfer extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'number',
        'date',
        'transaction_id',
        'remark',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'id' => 'string',
        'date' => 'date',
        'transaction_id' => 'string',
        'branch_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
        'deleted_by' => 'string',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

}


