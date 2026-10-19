<?php

namespace App\Models\Hr;

use App\Traits\CompanySpecific;
use App\Traits\HasCompany;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use App\Support\BranchContext;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Branch-level HR policy. Exactly one live row per branch.
 */
class HrSetting extends Model
{
    use HasFactory, HasUlids, HasUserTracking, HasUserAuditable, CompanySpecific, HasCompany, SoftDeletes;

    protected $table = 'hr_settings';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'leave_year_start_month',
        'payroll_cutoff_day',
        'overtime_multiplier',
        'default_probation_months',
        'default_notice_period_days',
        'self_service_enabled',
        'enforce_geofence',
        'geofence_latitude',
        'geofence_longitude',
        'geofence_radius_meters',
        'allowed_ip_ranges',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'company_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'leave_year_start_month' => 'integer',
            'payroll_cutoff_day' => 'integer',
            'overtime_multiplier' => 'decimal:4',
            'default_probation_months' => 'integer',
            'default_notice_period_days' => 'integer',
            'self_service_enabled' => 'boolean',
            'enforce_geofence' => 'boolean',
            'geofence_latitude' => 'decimal:7',
            'geofence_longitude' => 'decimal:7',
            'geofence_radius_meters' => 'integer',
            'allowed_ip_ranges' => 'array',
        ];
    }

    /**
     * The acting company's HR settings, created with defaults on first read.
     *
     * Shift rules, leave policy and salary components are company-level, so
     * these settings are too — branches of one company share one payroll
     * policy.
     *
     * Callers can rely on getting a row back rather than null-checking, which
     * is what keeps `$settings->overtime_multiplier` safe everywhere.
     */
    public static function forCompany(?string $companyId = null): self
    {
        $companyId = $companyId ?? static::activeCompanyId();

        return static::withoutGlobalScopes()
            ->firstOrCreate(
                ['company_id' => $companyId, 'deleted_at' => null],
                ['created_by' => auth()->id()]
            );
    }
}
