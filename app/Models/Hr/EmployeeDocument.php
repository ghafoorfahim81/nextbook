<?php

namespace App\Models\Hr;

use App\Enums\EmployeeDocumentType;
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
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

class EmployeeDocument extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasAttachments, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'employee_id',
        'document_type',
        'document_number',
        'issued_by',
        'issue_date',
        'expiry_date',
        'is_verified',
        'verified_by',
        'verified_at',
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
            'verified_by' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'document_type' => EmployeeDocumentType::class,
            'issue_date' => 'date',
            'expiry_date' => 'date',
            'verified_at' => 'datetime',
            'last_reminded_at' => 'datetime',
            'is_verified' => 'boolean',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'document_number',
            'issued_by',
            'employee.full_name',
            'employee.code',
        ];
    }

    protected array $allowedFilters = [
        'employee_id',
        'document_type',
        'is_verified',
        'expiry_date',
        'created_by',
    ];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function daysUntilExpiry(?Carbon $asOf = null): ?int
    {
        if (! $this->expiry_date) {
            return null;
        }

        return ($asOf ?? Carbon::today())->startOfDay()->diffInDays($this->expiry_date->startOfDay(), false);
    }

    public function isExpired(?Carbon $asOf = null): bool
    {
        $days = $this->daysUntilExpiry($asOf);

        return $days !== null && $days < 0;
    }

    /**
     * Documents inside their reminder window not yet flagged today.
     *
     * Already-expired documents are excluded on purpose: they need a report,
     * not a daily notification that repeats forever.
     */
    public function scopeDueForExpiryReminder($query, ?Carbon $asOf = null)
    {
        $asOf = $asOf ?? Carbon::today();

        return $query
            ->whereNotNull('expiry_date')
            ->whereRaw('expiry_date <= (?::date + (reminder_days_before || \' days\')::interval)', [$asOf->toDateString()])
            ->where('expiry_date', '>=', $asOf->toDateString())
            ->where(function ($q) use ($asOf) {
                $q->whereNull('last_reminded_at')
                    ->orWhere('last_reminded_at', '<', $asOf->startOfDay());
            });
    }
}
