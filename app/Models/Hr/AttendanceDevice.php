<?php

namespace App\Models\Hr;

use App\Enums\AttendanceDeviceType;
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

class AttendanceDevice extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'device_type',
        'serial_number',
        'location',
        'ip_address',
        'is_active',
        'last_sync_at',
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
            'device_type' => AttendanceDeviceType::class,
            'is_active' => 'boolean',
            'last_sync_at' => 'datetime',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'code', 'serial_number', 'location'];
    }

    protected array $allowedFilters = ['device_type', 'is_active', 'created_by'];

    protected function getRelationships(): array
    {
        return [
            'mappings' => [
                'model' => 'attendance_device_users',
                'message' => 'This device has employee mappings',
            ],
        ];
    }

    public function mappings(): HasMany
    {
        return $this->hasMany(AttendanceDeviceUser::class);
    }

    public function punches(): HasMany
    {
        return $this->hasMany(AttendancePunch::class);
    }
}
