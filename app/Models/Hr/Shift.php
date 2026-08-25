<?php

namespace App\Models\Hr;

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
use Illuminate\Support\Carbon;

class Shift extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'start_time',
        'end_time',
        'crosses_midnight',
        'break_minutes',
        'grace_in_minutes',
        'grace_out_minutes',
        'full_day_hours',
        'half_day_hours',
        'working_days',
        'is_default',
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
            'crosses_midnight' => 'boolean',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'break_minutes' => 'integer',
            'grace_in_minutes' => 'integer',
            'grace_out_minutes' => 'integer',
            'full_day_hours' => 'decimal:2',
            'half_day_hours' => 'decimal:2',
            'working_days' => 'array',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'code', 'remark'];
    }

    protected array $allowedFilters = ['is_active', 'is_default', 'created_by'];

    protected function getRelationships(): array
    {
        return [
            'employees' => [
                'model' => 'employees',
                'message' => 'This shift is assigned to employees',
            ],
        ];
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'shift_id');
    }

    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Whether this shift expects work on the given date.
     *
     * ISO-8601 weekday numbering (1 = Monday … 7 = Sunday), which is what
     * `working_days` stores. The Afghan private-sector default is 6,7,1,2,3,4
     * — Saturday through Thursday, with Friday (5) as the rest day.
     */
    public function worksOn(Carbon $date): bool
    {
        return in_array((int) $date->isoWeekday(), $this->workingDays(), true);
    }

    /**
     * `working_days` as a list of ISO weekday integers.
     *
     * The cast can still hand back a JSON string for a row written before the
     * double-encoding fix, so decode that case rather than fataling on it.
     */
    public function workingDays(): array
    {
        $days = $this->working_days ?: [];

        if (is_string($days)) {
            $days = json_decode($days, true) ?: [];
        }

        return array_map('intval', (array) $days);
    }

    /**
     * Shift start as a full timestamp on the given date.
     */
    public function startOn(Carbon $date): Carbon
    {
        return $this->applyTime($date->copy(), (string) $this->start_time);
    }

    /**
     * Shift end as a full timestamp, rolled to the next day for a night shift.
     */
    public function endOn(Carbon $date): Carbon
    {
        $end = $this->applyTime($date->copy(), (string) $this->end_time);

        return $this->crosses_midnight ? $end->addDay() : $end;
    }

    /**
     * Half day defaults to half of a full day when it is not configured, which
     * is what nearly every employer means by it.
     */
    public function halfDayHours(): float
    {
        return (float) ($this->half_day_hours ?? ((float) $this->full_day_hours / 2));
    }

    private function applyTime(Carbon $date, string $time): Carbon
    {
        [$h, $m, $s] = array_pad(explode(':', $time), 3, '0');

        return $date->setTime((int) $h, (int) $m, (int) $s);
    }
}
