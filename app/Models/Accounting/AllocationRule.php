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
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;

/**
 * How a shared cost is split across branches (phase 7): head-office rent,
 * 50% Kabul, 30% Herat, 20% Mazar.
 *
 * The split is dated. The shares with the same effective_from are one version;
 * an expense uses the latest version on or before its date, so changing the
 * split from next month on leaves every earlier expense, and every report
 * over it, as it was.
 */
class AllocationRule extends Model
{
    use HasUlids, HasSearch, HasSorting, CompanySpecific, HasCompany, HasUserAuditable, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'description',
        'is_active',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'id' => 'string',
        'is_active' => 'boolean',
    ];

    protected static function searchableColumns(): array
    {
        return ['name', 'description'];
    }

    public function shares(): HasMany
    {
        return $this->hasMany(AllocationRuleShare::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(\App\Models\Expense\Expense::class, 'allocation_rule_id');
    }

    protected function getRelationships(): array
    {
        return [
            'expenses' => ['model' => 'expenses', 'message' => __('messages.dependency.allocation_rule_used')],
        ];
    }

    /**
     * The shares in force on a date: the latest version on or before it.
     * Empty when the rule did not exist yet.
     *
     * @return Collection<int, AllocationRuleShare>
     */
    public function sharesOn(string $date): Collection
    {
        $version = $this->shares()
            ->whereDate('effective_from', '<=', $date)
            ->max('effective_from');

        if ($version === null) {
            return collect();
        }

        return $this->shares()
            ->whereDate('effective_from', $version)
            ->orderBy('id')
            ->get();
    }

    /**
     * Every version, newest first: [effective_from => shares].
     *
     * @return Collection<string, Collection<int, AllocationRuleShare>>
     */
    public function versions(): Collection
    {
        return $this->shares()
            ->with('branch:id,name')
            ->orderByDesc('effective_from')
            ->orderBy('id')
            ->get()
            ->groupBy(fn (AllocationRuleShare $share) => $share->effective_from->toDateString());
    }
}
