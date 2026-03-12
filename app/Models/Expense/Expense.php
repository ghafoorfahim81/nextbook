<?php

namespace App\Models\Expense;

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
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\BranchSpecific;
use App\Models\Transaction\Transaction;
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
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'date' => 'date',
        'category_id' => 'string',
        'rate' => 'float',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return ['date', 'remarks'];
    }

    protected array $allowedFilters = [
        'category_id',
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

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }


    protected function getRelationships(): array
    {
        return [];
    }
}

