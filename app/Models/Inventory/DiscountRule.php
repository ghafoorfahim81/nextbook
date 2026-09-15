<?php

namespace App\Models\Inventory;

use App\Enums\DiscountScope;
use App\Enums\DiscountType;
use App\Models\Administration\Brand;
use App\Models\Administration\Category;
use App\Models\Administration\CustomerGroup;
use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * An automatic sale discount and the conditions under which it fires.
 */
class DiscountRule extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasUserTracking, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'name',
        'scope',
        'scope_id',
        'discount_type',
        'value',
        'customer_group_id',
        'min_quantity',
        'starts_at',
        'ends_at',
        'priority',
        'is_active',
        'show_on_invoice',
        'branch_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'scope' => DiscountScope::class,
            'scope_id' => 'string',
            'discount_type' => DiscountType::class,
            'value' => 'decimal:4',
            'customer_group_id' => 'string',
            'min_quantity' => 'decimal:4',
            'starts_at' => 'date',
            'ends_at' => 'date',
            'priority' => 'integer',
            'is_active' => 'boolean',
            'show_on_invoice' => 'boolean',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'value'];
    }

    public function customerGroup(): \Illuminate\Database\Eloquent\Relations\BelongsTo
    {
        return $this->belongsTo(CustomerGroup::class);
    }

    /**
     * The record this rule targets. Not a polymorphic relation: scope_id points
     * at a different table per scope and is null for ALL.
     */
    public function target(): ?Model
    {
        if (! $this->scope->requiresTarget() || blank($this->scope_id)) {
            return null;
        }

        return match ($this->scope) {
            DiscountScope::ITEM => Item::find($this->scope_id),
            DiscountScope::CATEGORY => Category::find($this->scope_id),
            DiscountScope::BRAND => Brand::find($this->scope_id),
            default => null,
        };
    }

    /**
     * How strongly this rule claims a line. A rule tied to a customer group
     * always beats the same scope without one, so a wholesale deal never leaks
     * to walk-in retail.
     */
    public function specificity(): int
    {
        return $this->scope->specificity() + (filled($this->customer_group_id) ? 10 : 0);
    }

    /**
     * The money coming off one sale line.
     *
     * Percentage is taken off the line total, currency is a flat amount off the
     * line — both are line totals, matching how sale_items.discount is stored.
     * Never returns more than the line is worth.
     */
    public function discountFor(float $quantity, float $unitPrice): float
    {
        $lineTotal = $quantity * $unitPrice;

        if ($lineTotal <= 0) {
            return 0.0;
        }

        $discount = $this->discount_type === DiscountType::PERCENTAGE
            ? $lineTotal * ((float) $this->value / 100)
            : (float) $this->value;

        return round(max(0.0, min($discount, $lineTotal)), 4);
    }
}
