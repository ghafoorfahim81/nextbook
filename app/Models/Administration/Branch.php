<?php

namespace App\Models\Administration;

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
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, HasDependencyCheck, SoftDeletes;

    protected $table = 'branches';
    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing



    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'is_main',
        'parent_id',
        'location',
        'sub_domain',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'location',
            'sub_domain',
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
        'parent_id' => 'string',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];

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
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This branch is used in items'
            ],
            'userAccounts' => [
                'model' => 'accounts',
                'message' => 'This branch is used in accounts'
            ],
            'userLedgers' => [
                'model' => 'ledgers',
                'message' => 'This branch is used in customers or suppliers'
            ],
            'transactions' => [
                'model' => 'transactions',
                'message' => 'This branch is used in transactions'
            ],
            'children' => [
                'model' => 'subbranches',
                'message' => 'This branch has subbranches'
            ]
        ];
    }

    /**
     * Relationship to items that belong to this branch
     *
     * Every relation below drops the `branchSpecific` scope. The related models add
     * `branch_id = <acting branch>` of their own, which combined with this relation's
     * `branch_id = <this branch>` can only ever match the branch you are currently
     * in — so the dependency check used to report zero for every other branch and
     * let a branch full of data be deleted.
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'branch_id')
            ->withoutGlobalScope('branchSpecific');
    }

    /**
     * Relationship to accounts that belong to this branch
     */
    public function accounts()
    {
        return $this->hasMany(\App\Models\Account\Account::class, 'branch_id')
            ->withoutGlobalScope('branchSpecific');
    }

    /**
     * Relationship to ledgers that belong to this branch
     */
    public function ledgers()
    {
        return $this->hasMany(\App\Models\Ledger\Ledger::class, 'branch_id')
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

    /**
     * Accounts the user added themselves.
     *
     * Every branch is provisioned with the default chart of accounts, so counting
     * all accounts would make every branch permanently undeletable.
     */
    public function userAccounts()
    {
        return $this->accounts()->whereNotIn(
            'slug',
            array_column(\App\Models\Account\Account::defaultAccounts(), 'slug')
        );
    }

    /**
     * Customers and suppliers the user added themselves, ignoring the cash customer
     * every branch is provisioned with.
     */
    public function userLedgers()
    {
        return $this->ledgers()->where('code', '!=', 'CASH-CUST');
    }
}
