<?php

namespace App\Models\Accounting;

use App\Enums\FinancialPeriodStatus;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class FiscalYear extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $table = 'fiscal_years';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'start_date',
        'end_date',
        'status',
        'closed_at',
        'closed_by',
        'branch_id',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'status' => FinancialPeriodStatus::class,
            'closed_at' => 'datetime',
            'branch_id' => 'string',
            'closed_by' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name'];
    }

    public function periods(): HasMany
    {
        return $this->hasMany(FinancialPeriod::class, 'fiscal_year_id')->orderBy('start_date');
    }

    public function closedBy(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class, 'closed_by');
    }

    public function isClosed(): bool
    {
        return $this->status === FinancialPeriodStatus::Closed;
    }

    public function isOpen(): bool
    {
        return ! $this->isClosed();
    }
}
