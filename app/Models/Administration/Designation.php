<?php

namespace App\Models\Administration;

use App\Models\Hr\Employee;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasCache;
use App\Traits\HasDependencyCheck;
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
 * Job titles.
 *
 * This model used HasUuids against a char(26) ULID column, so every insert
 * generated a 36-character UUIDv4 that Postgres rejected outright — the module
 * could not create a single row. It also lacked BranchSpecific, so had inserts
 * worked, one branch would have seen another's designations.
 */
class Designation extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, HasCache, HasDependencyCheck, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'code',
        'department_id',
        'grade_level',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'department_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'grade_level' => 'integer',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'code',
            'remark',
            'department.name',
        ];
    }

    protected array $allowedFilters = [
        'name',
        'code',
        'department_id',
        'grade_level',
        'created_by',
    ];

    protected function getRelationships(): array
    {
        return [
            'employees' => [
                'model' => 'employees',
                'message' => 'This designation is used by employees',
            ],
        ];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'department_id');
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'designation_id');
    }
}
