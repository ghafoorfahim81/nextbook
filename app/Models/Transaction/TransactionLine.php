<?php

namespace App\Models\Transaction;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Account\Account;
class TransactionLine extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, SoftDeletes, BranchSpecific, HasBranch;

    protected $table = 'transaction_lines';
    protected $primaryKey = 'id';
    public $incrementing = false;
    protected $keyType = 'string';
    protected $fillable = [
        'transaction_id',
        'account_id',
        'debit',
        'credit',
        'remark',
        'created_by',
        'updated_by',
        'deleted_by',
        'branch_id',
    ];

    public function casts(): array
    {
        return [
            'id' => 'string',
            'transaction_id' => 'string',
            'account_id' => 'string',
            'debit' => 'float',
            'credit' => 'float',
        ];
    }

    public function transaction()
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function account()
    {
        return $this->belongsTo(Account::class, 'account_id');
    }
}
