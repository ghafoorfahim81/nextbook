<?php

namespace App\Models\Administration;

use App\Traits\CompanySpecific;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasCache;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class Branch extends Model
{
    // CompanySpecific: a branch is visible only inside its own company. Without
    // it the branch list, the "main branch" lookup and every Branch::query()
    // fallback read across companies.
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, HasDependencyCheck, CompanySpecific, SoftDeletes;

    protected $table = 'branches';
    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing



    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'name',
        'is_main',
        'parent_id',
        'location',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'location',
            'remark',
            'parent.name'
        ];
    }

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'is_main' => 'boolean',
        'company_id' => 'string',
        'parent_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
    public function children()
    {
        return $this->hasMany(Branch::class, 'parent_id');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        // Catalogue, chart of accounts and customers are company-level and
        // shared by every branch, so they are no longer a reason a branch
        // cannot be deleted — deleting one branch of three leaves them
        // untouched. What still blocks deletion is what the branch itself
        // owns: its documents, its staff, its warehouses and its sub-branches.
        return [
            'transactions' => [
                'model' => 'transactions',
                'message' => 'This branch is used in transactions'
            ],
            'children' => [
                'model' => 'subbranches',
                'message' => 'This branch has subbranches'
            ],
            'employees' => [
                'model' => 'employees',
                'message' => 'This branch is used in employees'
            ],
            'warehouses' => [
                'model' => 'warehouses',
                'message' => 'This branch is used in warehouses'
            ]
        ];
    }

    /**
     * Employees working at this branch.
     *
     * Every relation below drops the `branchSpecific` scope. The related models
     * add `branch_id = <acting branch>` of their own, which combined with this
     * relation's `branch_id = <this branch>` can only ever match the branch you
     * are currently in — so the dependency check used to report zero for every
     * other branch and let a branch full of data be deleted.
     */
    public function employees()
    {
        return $this->hasMany(\App\Models\Hr\Employee::class, 'branch_id')
            ->withoutGlobalScope('branchSpecific');
    }

    /**
     * Warehouses this branch operates. Owned by the company, but a branch
     * holding stock is not one that can be deleted.
     */
    public function warehouses()
    {
        return $this->hasMany(\App\Models\Administration\Warehouse::class, 'branch_id')
            ->withoutGlobalScope('branchSpecific');
    }

    /**
     * Relationship to transactions that belong to this branch
     */
    public function transactions()
    {
        return $this->hasMany(\App\Models\Transaction\Transaction::class, 'branch_id')
            ->withoutGlobalScope('branchSpecific');
    }
}
