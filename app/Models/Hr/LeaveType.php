<?php

namespace App\Models\Hr;

use App\Enums\Gender;
use App\Enums\LeaveAccrualMethod;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasDynamicFilters;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class LeaveType extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'colour',
        'is_paid',
        'accrual_method',
        'days_per_year',
        'accrual_rate_per_month',
        'max_carry_forward_days',
        'carry_forward_expiry_months',
        'max_consecutive_days',
        'min_notice_days',
        'min_service_months',
        'applicable_gender',
        'requires_attachment',
        'requires_approval',
        'deduct_from_salary',
        'is_encashable',
        'pro_rata_on_join',
        'excludes_holidays',
        'excludes_weekends',
        'is_active',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'accrual_method' => LeaveAccrualMethod::class,
            'applicable_gender' => Gender::class,
            'is_paid' => 'boolean',
            'requires_attachment' => 'boolean',
            'requires_approval' => 'boolean',
            'deduct_from_salary' => 'boolean',
            'is_encashable' => 'boolean',
            'pro_rata_on_join' => 'boolean',
            'excludes_holidays' => 'boolean',
            'excludes_weekends' => 'boolean',
            'is_active' => 'boolean',
            'days_per_year' => 'decimal:2',
            'accrual_rate_per_month' => 'decimal:2',
            'max_carry_forward_days' => 'decimal:2',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'code', 'remark'];
    }

    protected array $allowedFilters = ['accrual_method', 'is_paid', 'is_active', 'created_by'];

    protected function getRelationships(): array
    {
        return [
            'requests' => [
                'model' => 'leave_requests',
                'message' => 'This leave type is used in leave requests',
            ],
            'allocations' => [
                'model' => 'leave_allocations',
                'message' => 'This leave type has allocations',
            ],
        ];
    }

    public function requests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(LeaveAllocation::class);
    }

    /**
     * Whether this type is available to an employee at all.
     *
     * Covers the gender restriction (maternity) and the minimum service period
     * (Hajj leave typically requires years of service).
     */
    public function isAvailableTo(Employee $employee, ?\Illuminate\Support\Carbon $asOf = null): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->applicable_gender && $employee->gender !== $this->applicable_gender) {
            return false;
        }

        if ($this->min_service_months) {
            $asOf = $asOf ?? \Illuminate\Support\Carbon::today();
            $joined = $employee->joining_date;

            if (! $joined || $joined->copy()->addMonths($this->min_service_months)->gt($asOf)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Default types seeded per branch. Figures follow Afghan Labour Law as a
     * starting point and are fully editable — every one of these is data, not
     * code, precisely so an employer can set their own policy.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultLeaveTypes(): array
    {
        return [
            [
                'name' => 'Annual Leave',
                'code' => 'ANNUAL',
                'colour' => '#22c55e',
                'is_paid' => true,
                'accrual_method' => LeaveAccrualMethod::AnnualGrant->value,
                'days_per_year' => 20,
                'max_carry_forward_days' => 10,
                'carry_forward_expiry_months' => 6,
                'min_notice_days' => 3,
                'pro_rata_on_join' => true,
                'is_encashable' => true,
            ],
            [
                'name' => 'Sick Leave',
                'code' => 'SICK',
                'colour' => '#f59e0b',
                'is_paid' => true,
                'accrual_method' => LeaveAccrualMethod::AnnualGrant->value,
                'days_per_year' => 20,
                'requires_attachment' => false,
                'pro_rata_on_join' => true,
            ],
            [
                'name' => 'Emergency Leave',
                'code' => 'EMERGENCY',
                'colour' => '#ef4444',
                'is_paid' => true,
                'accrual_method' => LeaveAccrualMethod::AnnualGrant->value,
                'days_per_year' => 10,
                'pro_rata_on_join' => true,
            ],
            [
                'name' => 'Maternity Leave',
                'code' => 'MATERNITY',
                'colour' => '#ec4899',
                'is_paid' => true,
                'accrual_method' => LeaveAccrualMethod::Manual->value,
                'days_per_year' => 90,
                'applicable_gender' => Gender::Female->value,
                'excludes_weekends' => false,
                'excludes_holidays' => false,
                'pro_rata_on_join' => false,
            ],
            [
                'name' => 'Hajj Leave',
                'code' => 'HAJJ',
                'colour' => '#8b5cf6',
                'is_paid' => true,
                'accrual_method' => LeaveAccrualMethod::Manual->value,
                'days_per_year' => 45,
                // Granted once in a working life, so it is not accrued yearly.
                'min_service_months' => 24,
                'excludes_weekends' => false,
                'excludes_holidays' => false,
                'pro_rata_on_join' => false,
            ],
            [
                'name' => 'Unpaid Leave',
                'code' => 'UNPAID',
                'colour' => '#64748b',
                'is_paid' => false,
                'accrual_method' => LeaveAccrualMethod::Unlimited->value,
                'deduct_from_salary' => true,
                'pro_rata_on_join' => false,
            ],
        ];
    }
}
