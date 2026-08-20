<?php

namespace App\Models\Hr;

use App\Enums\HalfDayPeriod;
use App\Enums\LeaveRequestStatus;
use App\Models\Concerns\HasSequentialNumber;
use App\Models\User;
use App\Traits\BranchSpecific;
use App\Traits\HasAttachments;
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

class LeaveRequest extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasAttachments, HasSequentialNumber, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'number',
        'employee_id',
        'leave_type_id',
        'leave_allocation_id',
        'from_date',
        'to_date',
        'is_half_day',
        'half_day_period',
        'days',
        'reason',
        'contact_during_leave',
        'handover_to_id',
        'status',
        'applied_at',
        'approved_by',
        'approved_at',
        'rejected_by',
        'rejected_at',
        'rejection_reason',
        'cancelled_by',
        'cancelled_at',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'employee_id' => 'string',
            'leave_type_id' => 'string',
            'leave_allocation_id' => 'string',
            'handover_to_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'from_date' => 'date',
            'to_date' => 'date',
            'is_half_day' => 'boolean',
            'half_day_period' => HalfDayPeriod::class,
            'days' => 'decimal:2',
            'status' => LeaveRequestStatus::class,
            'applied_at' => 'datetime',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['number', 'reason', 'employee.full_name', 'employee.code', 'leaveType.name'];
    }

    protected array $allowedFilters = [
        'employee_id',
        'leave_type_id',
        'status',
        'from_date',
        'to_date',
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

    public function allocation(): BelongsTo
    {
        return $this->belongsTo(LeaveAllocation::class, 'leave_allocation_id');
    }

    public function handoverTo(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'handover_to_id');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * Attendance rows generated when this request was approved.
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    public function statusEnum(): LeaveRequestStatus
    {
        return $this->status instanceof LeaveRequestStatus
            ? $this->status
            : (LeaveRequestStatus::tryFrom((string) $this->status) ?? LeaveRequestStatus::Draft);
    }

    public function canTransitionTo(LeaveRequestStatus $target): bool
    {
        return $this->statusEnum()->canTransitionTo($target);
    }

    /**
     * Requests that hold dates against an employee. Used by the overlap guard.
     */
    public function scopeBlocking($query)
    {
        return $query->whereIn('status', array_values(array_filter(
            LeaveRequestStatus::values(),
            fn (string $s) => LeaveRequestStatus::from($s)->blocksOverlap()
        )));
    }

    public function scopeOverlapping($query, string $from, string $to)
    {
        return $query->whereDate('from_date', '<=', $to)
            ->whereDate('to_date', '>=', $from);
    }

    public function scopeApproved($query)
    {
        return $query->where('status', LeaveRequestStatus::Approved->value);
    }
}
