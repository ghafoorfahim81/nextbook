<?php

namespace App\Models\Hr;

use App\Enums\AttendanceSource;
use App\Enums\AttendanceStatus;
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
 * One derived row per employee per date.
 *
 * Never registered for activity logging: a single branch produces roughly 150k
 * of these plus their punches per year, which would dwarf the rest of the audit
 * trail without telling anyone anything they cannot already see here.
 */
class Attendance extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'date',
        'shift_id',
        'check_in',
        'check_out',
        'worked_hours',
        'overtime_hours',
        'break_minutes',
        'late_minutes',
        'early_out_minutes',
        'status',
        'leave_request_id',
        'source',
        'needs_review',
        'payroll_id',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'employee_id' => 'string',
            'shift_id' => 'string',
            'leave_request_id' => 'string',
            'payroll_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'date' => 'date',
            'check_in' => 'datetime',
            'check_out' => 'datetime',
            'worked_hours' => 'decimal:2',
            'overtime_hours' => 'decimal:2',
            'break_minutes' => 'integer',
            'late_minutes' => 'integer',
            'early_out_minutes' => 'integer',
            'status' => AttendanceStatus::class,
            'source' => AttendanceSource::class,
            'needs_review' => 'boolean',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['employee.full_name', 'employee.code', 'remark'];
    }

    protected array $allowedFilters = [
        'employee_id',
        'shift_id',
        'status',
        'source',
        'needs_review',
        'date',
        'created_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function shift(): BelongsTo
    {
        return $this->belongsTo(Shift::class);
    }

    public function leaveRequest(): BelongsTo
    {
        return $this->belongsTo(LeaveRequest::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }

    /**
     * A day consumed by a posted payroll is frozen. Editing it would leave the
     * payslip describing hours that no longer exist.
     */
    public function isLocked(): bool
    {
        return $this->payroll_id !== null;
    }

    public function scopeLocked($query)
    {
        return $query->whereNotNull('payroll_id');
    }

    public function scopeUnlocked($query)
    {
        return $query->whereNull('payroll_id');
    }

    public function scopeForPeriod($query, string $from, string $to)
    {
        return $query->whereDate('date', '>=', $from)->whereDate('date', '<=', $to);
    }
}
