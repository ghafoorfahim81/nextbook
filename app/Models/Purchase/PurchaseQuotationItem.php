<?php

namespace App\Models\Purchase;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use App\Traits\CompanySpecific;
use App\Traits\HasCompanyFromParent;

class PurchaseQuotationItem extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserAuditable, HasDependencyCheck, SoftDeletes, CompanySpecific, HasCompanyFromParent;

    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'company_id',
        'purchase_quotation_id',
        'item_id',
        'variant_id',
        'quantity',
        'free',
        'unit_price',
        'unit_measure_id',
        'batch',
        'expire_date',
        'category_id',
        'discount',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'purchase_quotation_id' => 'string',
            'item_id' => 'string',
            'variant_id' => 'string',
            'quantity' => 'decimal:2',
            'free' => 'decimal:2',
            'unit_price' => 'decimal:4',
            'unit_measure_id' => 'string',
            'batch' => 'string',
            'expire_date' => 'date',
            'category_id' => 'string',
            'discount' => 'decimal:2',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'batch',
            'expire_date',
            'quantity',
            'unit_price',
            'discount',
            'purchaseQuotation.number',
            'item.name',
            'item.code',
            'unitMeasure.name',
        ];
    }

    public function purchaseQuotation(): BelongsTo
    {
        return $this->belongsTo(PurchaseQuotation::class);
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\Item::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Inventory\ItemVariant::class, 'variant_id');
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Category::class);
    }

    /** No branch of its own: the purchaseQuotation holds it, and the company comes from there too. */
    protected function companyParentRelation(): string
    {
        return 'purchaseQuotation';
    }
}
