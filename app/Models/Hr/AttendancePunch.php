<?php

namespace App\Models\Hr;

use App\Enums\AttendanceSource;
use App\Enums\PunchDirection;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * The raw evidence: one row per reported timestamp, append-only.
 *
 * Punches are never edited to fix a day — the derived `attendances` row is
 * recomputed instead, so the original record of what a device reported survives
 * any later change to the pairing rules.
 */
class AttendancePunch extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'attendance_device_id',
        'employee_id',
        'device_user_id',
        'punched_at',
        'punch_direction',
        'source',
        'fingerprint',
        'attendance_id',
        'import_batch_id',
        'is_ignored',
        'latitude',
        'longitude',
        'ip_address',
        'user_agent',
        'raw_payload',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'attendance_device_id' => 'string',
            'employee_id' => 'string',
            'attendance_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'punched_at' => 'datetime',
            'punch_direction' => PunchDirection::class,
            'source' => AttendanceSource::class,
            'is_ignored' => 'boolean',
            'latitude' => 'decimal:7',
            'longitude' => 'decimal:7',
            'raw_payload' => 'array',
        ];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function device(): BelongsTo
    {
        return $this->belongsTo(AttendanceDevice::class, 'attendance_device_id');
    }

    public function attendance(): BelongsTo
    {
        return $this->belongsTo(Attendance::class);
    }

    /**
     * The dedupe key. Deterministic from the three things that identify one
     * physical punch, so the same export re-uploaded produces the same value
     * and the unique index rejects it in the database rather than in code.
     */
    public static function makeFingerprint(?string $deviceId, ?string $deviceUserId, string $punchedAtIso): string
    {
        return hash('sha256', implode('|', [
            $deviceId ?? '',
            $deviceUserId ?? '',
            $punchedAtIso,
        ]));
    }

    /** Punches whose device ID nobody has mapped to an employee yet. */
    public function scopeUnmapped($query)
    {
        return $query->whereNull('employee_id')->where('is_ignored', false);
    }

    public function scopeBetween($query, $from, $to)
    {
        return $query->where('punched_at', '>=', $from)->where('punched_at', '<=', $to);
    }
}
