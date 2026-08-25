<?php

namespace App\Models\Hr;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class TaxBracket extends Model
{
    use HasFactory, HasUlids, HasUserAuditable, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'tax_bracket_set_id', 'sequence', 'from_amount', 'to_amount',
        'fixed_amount', 'rate', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'tax_bracket_set_id' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'sequence' => 'integer',
            'from_amount' => 'decimal:4',
            'to_amount' => 'decimal:4',
            'fixed_amount' => 'decimal:4',
            'rate' => 'decimal:4',
        ];
    }

    public function set(): BelongsTo
    {
        return $this->belongsTo(TaxBracketSet::class, 'tax_bracket_set_id');
    }

    /**
     * Whether an amount falls in this band.
     *
     * The floor is inclusive only for the first band, so a value exactly on a
     * boundary belongs to the LOWER band — 12,500 is taxed at 2%, not 10%.
     * That is what makes the cumulative fixed amounts line up.
     */
    public function contains(float $amount): bool
    {
        $from = (float) $this->from_amount;
        $to = $this->to_amount === null ? null : (float) $this->to_amount;

        $aboveFloor = $from <= 0 ? $amount >= $from : $amount > $from;

        return $aboveFloor && ($to === null || $amount <= $to);
    }
}
