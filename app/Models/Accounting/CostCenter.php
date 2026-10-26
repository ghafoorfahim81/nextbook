<?php

namespace App\Models\Accounting;

use App\Traits\CompanySpecific;
use App\Traits\HasCompany;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A cost center (phase 7): where a cost belongs, independent of the branch
 * tree — "Head office", "Marketing", "Transport". Chosen on expenses and on
 * journal entry lines, and carried onto the ledger lines they post.
 */
class CostCenter extends Model
{
    use HasUlids, HasSearch, HasSorting, CompanySpecific, HasCompany, HasUserAuditable, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'parent_id',
        'code',
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'parent_id' => 'string',
        'is_active' => 'boolean',
    ];

    protected static function searchableColumns(): array
    {
        return ['name', 'code', 'description'];
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id');
    }

    protected function getRelationships(): array
    {
        return [
            'children' => ['model' => 'cost_centers', 'message' => __('messages.dependency.cost_center_has_children')],
            'transactionLines' => ['model' => 'transaction_lines', 'message' => __('messages.dependency.cost_center_used')],
        ];
    }

    public function transactionLines(): HasMany
    {
        return $this->hasMany(\App\Models\Transaction\TransactionLine::class, 'cost_center_id');
    }
}
