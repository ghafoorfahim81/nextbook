<?php

namespace App\Models\Hr;

use App\Enums\TaxPeriod;
use App\Models\Administration\Currency;
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
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxBracketSet extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasDynamicFilters, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name', 'jurisdiction', 'period', 'effective_from', 'effective_to',
        'currency_id', 'is_active', 'is_system', 'remark', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'currency_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'period' => TaxPeriod::class,
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
            'is_system' => 'boolean',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'jurisdiction', 'remark'];
    }

    protected array $allowedFilters = ['period', 'jurisdiction', 'is_active', 'effective_from'];

    /**
     * Drops the branch scope on purpose.
     *
     * A bracket belongs to its SET, not to whichever branch the request happens
     * to be acting on. With the scope left in place, resolving a set for another
     * branch — which provisioning, reporting and console commands all do —
     * returns a set with zero bands, and the tax engine would either throw or,
     * worse, tax nothing.
     */
    public function brackets(): HasMany
    {
        // By NAME, not by class: BranchSpecific registers its scope as the
        // string 'branchSpecific', so passing the class here silently removes
        // nothing (the same convention Branch::items() follows).
        return $this->hasMany(TaxBracket::class)
            ->withoutGlobalScope('branchSpecific')
            ->orderBy('sequence');
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * The Afghan monthly wage withholding table, as a starting point.
     *
     *   up to 5,000          0%
     *   5,001 – 12,500       2% of the excess over 5,000
     *   12,501 – 100,000     150 + 10% of the excess over 12,500
     *   above 100,000        8,900 + 20% of the excess over 100,000
     *
     * The fixed amounts are the cumulative tax at each band's floor, so they
     * are self-checking: 2% of 7,500 = 150, and 150 + 10% of 87,500 = 8,900.
     *
     * Seeded as editable data, not code. Verify against current Ministry of
     * Finance rules before go-live; when the law changes, edit the rows.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function defaultAfghanMonthlyBrackets(): array
    {
        return [
            ['sequence' => 1, 'from_amount' => 0, 'to_amount' => 5000, 'fixed_amount' => 0, 'rate' => 0],
            ['sequence' => 2, 'from_amount' => 5000, 'to_amount' => 12500, 'fixed_amount' => 0, 'rate' => 2],
            ['sequence' => 3, 'from_amount' => 12500, 'to_amount' => 100000, 'fixed_amount' => 150, 'rate' => 10],
            ['sequence' => 4, 'from_amount' => 100000, 'to_amount' => null, 'fixed_amount' => 8900, 'rate' => 20],
        ];
    }
}
