<?php

namespace App\Models\Hr;

use App\Enums\HolidayType;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class Holiday extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'date',
        'end_date',
        'holiday_type',
        'is_recurring',
        'is_paid',
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
            'date' => 'date',
            'end_date' => 'date',
            'holiday_type' => HolidayType::class,
            'is_recurring' => 'boolean',
            'is_paid' => 'boolean',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'remark'];
    }

    protected array $allowedFilters = ['holiday_type', 'is_paid', 'date', 'created_by'];

    /**
     * Holidays covering the given date.
     *
     * A holiday with no end_date is a single day; one with an end_date spans an
     * inclusive range, which is how Eid is entered as one record.
     */
    public function scopeCovering($query, Carbon|string $date)
    {
        $date = $date instanceof Carbon ? $date->toDateString() : $date;

        return $query->where(function ($q) use ($date) {
            $q->where(function ($inner) use ($date) {
                $inner->whereNull('end_date')->whereDate('date', $date);
            })->orWhere(function ($inner) use ($date) {
                $inner->whereNotNull('end_date')
                    ->whereDate('date', '<=', $date)
                    ->whereDate('end_date', '>=', $date);
            });
        });
    }

    /**
     * Holidays overlapping a date range, for the attendance and leave engines
     * which resolve a whole period at once rather than day by day.
     */
    public function scopeOverlapping($query, Carbon|string $from, Carbon|string $to)
    {
        $from = $from instanceof Carbon ? $from->toDateString() : $from;
        $to = $to instanceof Carbon ? $to->toDateString() : $to;

        return $query->where(function ($q) use ($from, $to) {
            $q->where(function ($inner) use ($from, $to) {
                $inner->whereNull('end_date')
                    ->whereDate('date', '>=', $from)
                    ->whereDate('date', '<=', $to);
            })->orWhere(function ($inner) use ($from, $to) {
                $inner->whereNotNull('end_date')
                    ->whereDate('date', '<=', $to)
                    ->whereDate('end_date', '>=', $from);
            });
        });
    }

    /**
     * Every calendar date this holiday covers.
     *
     * @return array<int, string>
     */
    public function coveredDates(): array
    {
        $start = $this->date instanceof Carbon ? $this->date->copy() : Carbon::parse($this->date);
        $end = $this->end_date
            ? ($this->end_date instanceof Carbon ? $this->end_date->copy() : Carbon::parse($this->end_date))
            : $start->copy();

        $dates = [];

        for ($cursor = $start->copy(); $cursor->lte($end); $cursor->addDay()) {
            $dates[] = $cursor->toDateString();
        }

        return $dates;
    }
}
