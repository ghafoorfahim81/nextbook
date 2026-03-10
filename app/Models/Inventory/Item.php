<?php

namespace App\Models\Inventory;

use App\Models\Inventory\StockOut;
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
use App\Enums\ItemType;
use App\Models\Inventory\StockBalance;
use App\Traits\HasUserTracking;
use App\Models\Transaction\Transaction;
use Illuminate\Database\Eloquent\Relations\HasOne;
use App\Enums\StockSourceType;
class Item extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, HasDynamicFilters, HasBranch, BranchSpecific, HasDependencyCheck, SoftDeletes;

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
        'unit_measure_id',
        'is_batch_tracked',
        'is_expiry_tracked',
        'brand_id',
        'category_id',
        'cost_account_id',
        'income_account_id',
        'asset_account_id',
        'minimum_stock',
        'maximum_stock',
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
            'item_type' => ItemType::class,
            'minimum_stock' => 'double',
            'maximum_stock' => 'double',
            'purchase_price' => 'double',
            'cost' => 'double',
            'sale_price' => 'double',
            'rate_a' => 'double',
            'rate_b' => 'double',
            'rate_c' => 'double',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'colors' => 'array',
            'is_batch_tracked' => 'boolean',
            'is_expiry_tracked' => 'boolean',
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
        'purchase_price',
        'sale_price',
        'created_by',
    ];

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

    public function avgCost()
    {
        return  $this->stockBalances()->avg('average_cost');
    }
    // public function inRecords()
    // {
    //     return $this->hasMany(Stock::class);
    // }
    // public function outRecords()
    // {
    //     return $this->hasMany(StockOut::class);
    // }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'stockOut' => [
                'model' => 'stock out records',
                'message' => 'This item has stock out records'
            ]
        ];
    }
}
