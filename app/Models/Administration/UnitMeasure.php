<?php

namespace App\Models\Administration;

use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;
use App\Traits\HasCache;
use App\Traits\BranchSpecific;
class UnitMeasure extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new Ulid(); // Generate ULID
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'unit',
        'symbol',
        'description',
        'is_active',
        'branch_id',
        'quantity_id',
        'is_main',
        'value',
        'created_by',
        'updated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */

    protected $casts = [
        'id' => 'string',
        'branch_id' => 'string',
        'quantity_id' => 'string',
        'is_active' => 'boolean',
        'is_main' => 'boolean',
        'value' => 'double',
        'created_by' => 'string',
        'updated_by' => 'string',
        'parent_id' => 'string',
    ];

    protected static function searchableColumns(): array
    {
        return [
        'name',
        'unit',
        'symbol',
        'quantity.quantity',
        'quantity.unit',
        'quantity.symbol',
        ];
    }

    public static function defaultUnitMeasures(): array
    {
        return [
            // count
            [
                'name'        => 'دانه',
                'unit'        => 1,
                'symbol'      => "ea",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'count')->first()->id,
                'quantity_slug' => 'count',
                'is_main'   => true,
            ],
            [
                'name'        => 'جوره',
                'unit'        => 2,
                'symbol'      => "pr",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'count')->first()->id,
                'quantity_slug' => 'count',
                'is_main'   => true,
            ],
            [
                'name'        => 'درجن',
                'unit'        => 12,
                'symbol'      => "dz",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'count')->first()->id,
                'quantity_slug' => 'count',
                'is_main'   => true,
            ],
            [
                'name'        => 'باکس',
                'unit'        => 6,
                'symbol'      => "box",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'count')->first()->id,
                'quantity_slug' => 'count',
                'is_main'     => true,
            ],
            [
                'name'        => 'بوتل',
                'unit'        => 1,
                'symbol'      => "btl",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'count')->first()->id,
                'quantity_slug' => 'count',
                'is_main'     => true,
            ],
            // length
            [
                'name'        => 'سانتی متر',
                'unit'        => 1,
                'symbol'      => "cm",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'length')->first()->id,
                'quantity_slug' => 'length',
                'is_main'   => true,
            ],
            [
                'name'        => 'متر',
                'unit'        => 100,
                'symbol'      => "m",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'length')->first()->id,
                'quantity_slug' => 'length',
                'is_main'   => true,
            ],
            [
                'name'        => 'اینچ',
                'unit'        => 2.5,
                'symbol'      => "in",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'length')->first()->id,
                'quantity_slug' => 'length',
                'is_main'   => true,
            ],
            // area
            [
                'name'        => 'سانتی متر مربع',
                'unit'        => 1,
                'symbol'      => "cm2",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'area')->first()->id,
                'quantity_slug' => 'area',
                'is_main'   => true,
            ],
            [
                'name'        => 'دسی متر مربع',
                'unit'        => 0.01,
                'symbol'      => "dm2",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'area')->first()->id,
                'quantity_slug' => 'area',
                'is_main'   => true,
            ],
            [
                'name'        => 'متر مربع',
                'unit'        => 0.0001,
                'symbol'      => "m2",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'area')->first()->id,
                'quantity_slug' => 'area',
                'is_main'   => true,
            ],
            // weight
            [
                'name'        => 'گرم',
                'unit'        => 1,
                'symbol'      => "g",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'weight')->first()->id,
                'quantity_slug' => 'weight',
                'is_main'   => true,
            ],
            [
                'name'        => 'کیلوگرم',
                'unit'        => 1000,
                'symbol'      => "kg",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'weight')->first()->id,
                'quantity_slug' => 'weight',
                'is_main'   => true,
            ],
            [
                'name'        => 'تن',
                'unit'        => 1000000,
                'symbol'      => "ton",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'weight')->first()->id,
                'quantity_slug' => 'weight',
                'is_main'   => true,
            ],
            // volume
            [
                'name'        => 'میلی لیتر',
                'unit'        => 1,
                'symbol'      => "ml",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'volume')->first()->id,
                'quantity_slug' => 'volume',
                'is_main'   => true,
            ],
            [
                'name'        => 'لیتر',
                'unit'        => 1000,
                'symbol'      => "L",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'volume')->first()->id,
                'quantity_slug' => 'volume',
                'is_main'   => true,
            ],
            [
                        'name'        => 'گالون',
                'unit'        => 3785.41, // US Gallon to ml
                'symbol'      => "gal",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'volume')->first()->id,
                'quantity_slug' => 'volume',
                'is_main'   => true,
            ],
            [
                'name'        => 'باریل',
                'unit'        => 158987.294928, // نفت خام به ml
                'symbol'      => "bbl",
                'quantity_id' => Quantity::withoutGlobalScopes()->where('slug', 'volume')->first()->id,
                'quantity_slug' => 'volume',
                'is_main'   => true,
            ],
        ];
    }
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function quantity(): BelongsTo
    {
        return $this->belongsTo(Quantity::class);
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This unit measure is used in items'
            ],
            'stocks' => [
                'model' => 'stock records',
                'message' => 'This unit measure is used in stock records'
            ]
        ];
    }

    /**
     * Relationship to items that use this unit measure
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'unit_measure_id');
    }

    /**
     * Relationship to stocks that use this unit measure
     */
    public function stocks()
    {
        return $this->hasMany(\App\Models\Inventory\Stock::class, 'unit_measure_id');
    }

}
