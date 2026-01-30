<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Models\Transaction\Transaction;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
class ItemOpeningTransaction extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, SoftDeletes, BranchSpecific, HasBranch;

    protected $table = 'item_opening_transactions';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'item_id',
        'transaction_id',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    public function casts(): array
    {
        return [
            'id' => 'string',
            'branch_id' => 'string',
            'updated_by' => 'string',
            'deleted_by' => 'string',
            'item_id' => 'string',
            'transaction_id' => 'string',
            'opening_balance_transaction_id' => 'string',
        ];

    }

    public function item()
    {
        return $this->belongsTo(Item::class);
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

}
