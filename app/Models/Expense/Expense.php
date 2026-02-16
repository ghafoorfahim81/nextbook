<?php

namespace App\Models\Expense;

use App\Models\Account\Account;
use App\Models\Administration\Currency;
use App\Models\Transaction\Transaction;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BranchSpecific;
class Expense extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasDynamicFilters, BranchSpecific, HasBranch, HasUserAuditable, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'date',
        'remarks',
        'category_id',
        'rate',
        'attachment',
        'expense_transaction_id',
        'bank_transaction_id',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'date' => 'date',
        'category_id' => 'string',
        'rate' => 'float',
        'expense_transaction_id' => 'string',
        'bank_transaction_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return ['date', 'remarks'];
    }

    protected array $allowedFilters = [
        'category_id',
        'bankTransaction.lines.account_id',
        'expenseTransaction.lines.account_id',
        'date',
        'created_by',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'category_id');
    }

    public function details(): HasMany
    {
        return $this->hasMany(ExpenseDetail::class);
    }

    public function expenseTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'expense_transaction_id');
    }

    public function bankTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'bank_transaction_id');
    }

    // Get total amount from details
    public function getTotalAttribute(): float
    {
        return $this->details->sum('amount');
    }

    // Get total in base currency
    public function getBaseTotalAttribute(): float
    {
        return $this->total * ($this->rate ?? 1);
    }

    protected function getRelationships(): array
    {
        return [];
    }
}

