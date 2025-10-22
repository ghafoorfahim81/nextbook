<?php

namespace App\Models\Inventory;

use App\Models\StockOut;
use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasCache;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Item extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasCache, HasSearch, HasSorting, HasBranch, HasDependencyCheck, SoftDeletes;

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
        'generic_name',
        'packing',
        'barcode',
        'unit_measure_id',
        'brand_id',
        'category_id',
        'minimum_stock',
        'maximum_stock',
        'colors',
        'size',
        'photo',
        'purchase_price',
        'cost',
        'mrp_rate',
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
            'minimum_stock' => 'double',
            'maximum_stock' => 'double',
            'purchase_price' => 'double',
            'cost' => 'double',
            'mrp_rate' => 'double',
            'rate_a' => 'double',
            'rate_b' => 'double',
            'rate_c' => 'double',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
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
            'size',
            'photo',
            'purchase_price',
            'cost',
            'mrp_rate',
            'rate_a',
            'rate_b',
            'rate_c',
            'rack_no',
            'fast_search',
            'branch.name',
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

    public function branch(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Administration\Branch::class);
    }

    public function stocks()
    {
        return $this->hasMany(Stock::class);
    }

    public function openings()
    {
        return $this->hasMany(StockOpening::class, 'item_id', 'id');
    }

    public function stockOut()
    {
        return $this->hasMany(StockOut::class);
    }

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
