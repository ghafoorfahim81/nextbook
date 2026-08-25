<?php

namespace App\Models\Hr;

use App\Enums\LeaveAllocationSource;
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
 * One employee's entitlement to one leave type for one period.
 *
 * The balance is NOT stored here — only what was granted. Consumption is
 * derived from approved requests by LeaveBalanceService, so a cancelled request
 * or a reversed payroll cannot leave a stale number behind.
 */
class LeaveAllocation extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'leave_type_id',
        'period_start',
        'period_end',
        'entitled_days',
        'carried_forward_days',
        'adjustment_days',
        'encashed_days',
        'expired_days',
        'source',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'employee_id' => 'string',
            'leave_type_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'period_start' => 'date',
            'period_end' => 'date',
            'entitled_days' => 'decimal:2',
            'carried_forward_days' => 'decimal:2',
            'adjustment_days' => 'decimal:2',
            'encashed_days' => 'decimal:2',
            'expired_days' => 'decimal:2',
            'source' => LeaveAllocationSource::class,
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['employee.full_name', 'employee.code', 'leaveType.name'];
    }

    protected array $allowedFilters = [
        'employee_id',
        'leave_type_id',
        'source',
        'period_start',
        'created_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function leaveType(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class);
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * Everything granted, before anything is taken.
     */
    public function grantedDays(): float
    {
        return (float) $this->entitled_days
            + (float) $this->carried_forward_days
            + (float) $this->adjustment_days;
    }

    public function scopeCovering($query, string $date)
    {
        return $query->whereDate('period_start', '<=', $date)
            ->whereDate('period_end', '>=', $date);
    }
}
