<?php

namespace App\Models\Hr;

use App\Enums\PayFrequency;
use App\Enums\PayrollStatus;
use App\Models\Administration\Currency;
use App\Models\Administration\Department;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\Transaction\Transaction;
use App\Models\User;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * One payroll run for one period.
 *
 * Everything up to `approved` is free to recalculate — nothing has reached the
 * general ledger. Once posted the run is immutable: a correction is a reversal
 * plus a fresh run, so the original voucher and the correction both survive in
 * the audit trail.
 */
class Payroll extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasSequentialNumber, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'number', 'name', 'period_start', 'period_end', 'pay_date', 'period_label',
        'pay_frequency', 'currency_id', 'rate', 'status',
        'total_gross', 'total_deductions', 'total_tax', 'total_net', 'employee_count',
        'department_id', 'employment_type',
        'transaction_id', 'reversal_transaction_id',
        'approved_by', 'approved_at', 'posted_by', 'posted_at',
        'cancelled_by', 'cancelled_at', 'cancellation_reason',
        'remark', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'currency_id' => 'string',
            'department_id' => 'string',
            'transaction_id' => 'string',
            'reversal_transaction_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'period_start' => 'date',
            'period_end' => 'date',
            'pay_date' => 'date',
            'pay_frequency' => PayFrequency::class,
            'status' => PayrollStatus::class,
            'rate' => 'decimal:8',
            'total_gross' => 'decimal:4',
            'total_deductions' => 'decimal:4',
            'total_tax' => 'decimal:4',
            'total_net' => 'decimal:4',
            'employee_count' => 'integer',
            'approved_at' => 'datetime',
            'posted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['number', 'name', 'period_label', 'remark'];
    }

    protected array $allowedFilters = [
        'status', 'pay_frequency', 'department_id', 'employment_type',
        'period_start', 'period_end', 'created_by',
    ];

    public function lines(): HasMany
    {
        return $this->hasMany(PayrollLine::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'transaction_id');
    }

    public function reversalTransaction(): BelongsTo
    {
        return $this->belongsTo(Transaction::class, 'reversal_transaction_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    public function poster(): BelongsTo
    {
        return $this->belongsTo(User::class, 'posted_by');
    }

    /** Attendance days this run consumed and froze. */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function statusEnum(): PayrollStatus
    {
        return $this->status instanceof PayrollStatus
            ? $this->status
            : (PayrollStatus::tryFrom((string) $this->status) ?? PayrollStatus::Draft);
    }

    public function canTransitionTo(PayrollStatus $target): bool
    {
        return $this->statusEnum()->canTransitionTo($target);
    }

    public function isRecalculable(): bool
    {
        return $this->statusEnum()->isRecalculable();
    }

    public function isPosted(): bool
    {
        return $this->statusEnum()->isPosted();
    }

    public function scopePosted($query)
    {
        return $query->whereIn('status', [PayrollStatus::Posted->value, PayrollStatus::Paid->value]);
    }
}
