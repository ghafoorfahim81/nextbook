<?php

namespace App\Models\Accounting;

use App\Models\Account\Account;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AccountBalance extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch;

    protected $table = 'account_balances';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'financial_period_id',
        'account_id',
        'base_debit',
        'base_credit',
        'base_balance',
        'balance_type',
        'snapshot_at',
        'branch_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'financial_period_id' => 'string',
            'account_id' => 'string',
            'base_debit' => 'float',
            'base_credit' => 'float',
            'base_balance' => 'float',
            'snapshot_at' => 'datetime',
            'branch_id' => 'string',
            'created_by' => 'string',
        ];
    }

    public function financialPeriod(): BelongsTo
    {
        return $this->belongsTo(FinancialPeriod::class, 'financial_period_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(Account::class);
    }
}
