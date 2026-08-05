<?php

namespace App\Models\Inventory;

use App\Models\Inventory\StockOut;
use App\Traits\HasAttachments;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasCache;
use App\Traits\BranchSpecific;
use App\Traits\HasDynamicFilters;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\CostingMethod;
use App\Enums\ItemType;
use App\Enums\PricingMethod;
use App\Models\Inventory\StockBalance;
use App\Traits\HasUserTracking;
use App\Models\Transaction\Transaction;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\StockSourceType;
class Item extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, HasDynamicFilters, HasBranch, BranchSpecific, HasDependencyCheck, HasAttachments, SoftDeletes;

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'item_type',
        'sku',
        'generic_name',
        'packing',
        'barcode',
        'margin_percentage',
        'unit_measure_id',
        'is_batch_tracked',
        'is_expiry_tracked',
        'is_color_tracked',
        'is_size_tracked',
        'brand_id',
        'category_id',
        'cost_account_id',
        'income_account_id',
        'asset_account_id',
        'minimum_stock',
        'maximum_stock',
        'avg_cost',
        'colors',
        'size_id',
        'photo',
        'purchase_price',
        'cost',
        'sale_price',
        'rate_a',
        'rate_b',
        'rate_c',
        'rack_no',
        'fast_search',
        'branch_id',
        'created_by',
        'updated_by',

        // Behaviour flags — a business type is a combination of these.
        'is_active',
        'is_stockable',
        'is_sellable',
        'is_purchasable',
        'is_serial_tracked',
        'is_weighted',
        'has_variants',
        'allow_negative_stock',

        'costing_method',
        'pricing_method',

        'description',
        'weight',
        'length',
        'width',
        'height',

        'manufacturer',
        'model',
        'country_of_origin',
        'hs_code',
        'warranty_months',
        'shelf_life_days',
        'min_shelf_life_percent',
        'storage_zone',
        'requires_prescription',
        'is_controlled',

        'reorder_quantity',
        'lead_time_days',
        'default_warehouse_id',
        'attributes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'unit_measure_id' => 'string',
            'brand_id' => 'string',
            'category_id' => 'string',
            'size_id' => 'string',
            'margin_percentage' => 'decimal:4',
            'avg_cost' => 'decimal:4',
            'item_type' => ItemType::class,
            'minimum_stock' => 'decimal:2',
            'maximum_stock' => 'decimal:2',
            'purchase_price' => 'decimal:2',
            'cost' => 'decimal:2',
            'sale_price' => 'decimal:2',
            'rate_a' => 'decimal:2',
            'rate_b' => 'decimal:2',
            'rate_c' => 'decimal:2',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'colors' => 'array',
            'is_batch_tracked' => 'boolean',
            'is_expiry_tracked' => 'boolean',
            'is_color_tracked' => 'boolean',
            'is_size_tracked' => 'boolean',

            'is_active' => 'boolean',
            'is_stockable' => 'boolean',
            'is_sellable' => 'boolean',
            'is_purchasable' => 'boolean',
            'is_serial_tracked' => 'boolean',
            'is_weighted' => 'boolean',
            'has_variants' => 'boolean',
            'allow_negative_stock' => 'boolean',
            'requires_prescription' => 'boolean',
            'is_controlled' => 'boolean',

            'costing_method' => CostingMethod::class,
            'pricing_method' => PricingMethod::class,

            'weight' => 'decimal:4',
            'length' => 'decimal:4',
            'width' => 'decimal:4',
            'height' => 'decimal:4',
            'reorder_quantity' => 'decimal:4',
            'warranty_months' => 'integer',
            'shelf_life_days' => 'integer',
            'min_shelf_life_percent' => 'integer',
            'lead_time_days' => 'integer',
            'default_warehouse_id' => 'string',
            'attributes' => 'array',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'code',
            'generic_name',
            'packing',
            'barcode',
            'unitMeasure.name',
            'brand.name',
            'category.name',
            'minimum_stock',
            'maximum_stock',
            'avg_cost',
            'colors',
            'size.name',
            'purchase_price',
            'cost',
            'sale_price',
            'rate_a',
            'rate_b',
            'rate_c',
            'rack_no',
            'fast_search',
            'branch.name',
        ];
    }

    protected array $allowedFilters = [
        'code',
        'item_type',
        'unit_measure_id',
        'category_id',
        'size_id',
        'brand_id',
        'warehouse_id',
        'purchase_price',
        'sale_price',
        'created_by',
    ];

    /**
     * Custom filter handlers. Warehouse is not a column on items; instead we
     * match items that currently hold stock in the selected warehouse.
     */
    protected function dynamicFilterHandlers(): array
    {
        return [
            'warehouse_id' => function ($query, $value) {
                $query->whereHas('stockBalances', function ($balanceQuery) use ($value) {
                    if (is_array($value)) {
                        $balanceQuery->whereIn('warehouse_id', $value);
                        return;
                    }

                    $balanceQuery->where('warehouse_id', $value);
                });
            },
        ];
    }

    public function unitMeasure(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\UnitMeasure::class);
    }

    public function brand(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Brand::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Category::class);
    }

    public function size(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Size::class);
     }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class);
    }

    public function stocks()
    {
        return $this->hasMany(StockMovement::class);
    }

    public function openings()
    {
        return $this->hasMany(StockMovement::class, 'item_id', 'id')->where('source', StockSourceType::OPENING->value);
    }

    public function stockOut()
    {
        return $this->hasMany(StockOut::class);
    }
    public function openingTransaction(): HasOne
    {
        return $this->hasOne(Transaction::class, 'reference_id');
    }

    public function assetAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account\Account::class, 'asset_account_id');
    }

    public function incomeAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account\Account::class, 'income_account_id');
    }

    public function costAccount(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Account\Account::class, 'cost_account_id');
    }

    public function stockBalances()
    {
        return $this->hasMany(StockBalance::class);
    }

    /**
     * Sellable products under this catalogue entry. Every item has at least
     * one, so callers never need a null check.
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ItemVariant::class)->orderBy('sort_order');
    }

    public function defaultVariant(): HasOne
    {
        return $this->hasOne(ItemVariant::class)->where('is_default', true);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class);
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(StockPiece::class);
    }

    public function defaultWarehouse(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Warehouse::class, 'default_warehouse_id');
    }

    /**
     * Costing falls back to the company setting: a jewellery shop sells gold
     * (specific) and gift boxes (weighted average) from the same catalogue.
     */
    public function effectiveCostingMethod(): CostingMethod
    {
        if ($this->costing_method instanceof CostingMethod) {
            return $this->costing_method;
        }

        $companyDefault = \Illuminate\Support\Facades\Cache::get('costing_method');

        return CostingMethod::tryFrom((string) $companyDefault) ?? CostingMethod::WEIGHTED_AVERAGE;
    }

    public function onHand(): string
    {
        return $this->stockBalances()->sum('quantity');
    }
    public function onHandByStore(string $storeId): string
    {
      return (string) $this->stockBalances()
        ->where('store_id', $storeId)
        ->sum('quantity');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'stock_movements' => [
                'model' => 'stock_movements',
                'message' => 'This item has stock movements'
            ],
            'stock_balances' => [
                'model' => 'stock_balances',
                'message' => 'This item has stock balances'
            ]
        ];
    }
}
