<?php

namespace App\Models\Hr;

use App\Enums\ContractStatus;
use App\Enums\ContractType;
use App\Models\Administration\Currency;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class EmployeeContract extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasAttachments, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'contract_number',
        'contract_type',
        'start_date',
        'end_date',
        'is_current',
        'basic_salary',
        'currency_id',
        'probation_months',
        'notice_period_days',
        'working_hours_per_day',
        'working_days_per_week',
        'annual_leave_entitlement',
        'status',
        'renewed_from_id',
        'terminated_on',
        'termination_reason',
        'reminder_days_before',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'employee_id' => 'string',
            'currency_id' => 'string',
            'renewed_from_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'contract_type' => ContractType::class,
            'status' => ContractStatus::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'terminated_on' => 'date',
            'last_reminded_at' => 'datetime',
            'is_current' => 'boolean',
            'basic_salary' => 'decimal:4',
            'working_hours_per_day' => 'decimal:2',
            'annual_leave_entitlement' => 'decimal:2',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'contract_number',
            'employee.full_name',
            'employee.code',
        ];
    }

    protected array $allowedFilters = [
        'employee_id',
        'contract_type',
        'status',
        'is_current',
        'start_date',
        'end_date',
        'created_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_id');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    /**
     * Days until this contract lapses. Negative once it already has.
     *
     * Null for a contract with no end date, which is a permanent contract and
     * genuinely never expires — distinct from "expires today".
     */
    public function daysUntilExpiry(?Carbon $asOf = null): ?int
    {
        if (! $this->end_date) {
            return null;
        }

        return ($asOf ?? Carbon::today())->startOfDay()->diffInDays($this->end_date->startOfDay(), false);
    }

    /**
     * Contracts inside their reminder window that nobody has been told about
     * today. Drives the daily renewal reminder.
     */
    public function scopeDueForRenewalReminder($query, ?Carbon $asOf = null)
    {
        $asOf = $asOf ?? Carbon::today();

        return $query
            ->whereNotNull('end_date')
            ->where('status', ContractStatus::Active->value)
            ->whereRaw('end_date <= (?::date + (reminder_days_before || \' days\')::interval)', [$asOf->toDateString()])
            ->where('end_date', '>=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<', $asOf->startOfDay());
            });
    }
}
