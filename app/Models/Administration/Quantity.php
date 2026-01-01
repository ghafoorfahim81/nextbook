<?php

namespace App\Models\Administration;

use App\Traits\HasBranch;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;
use App\Traits\BranchSpecific;
class Quantity extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasSearch, HasSorting, BranchSpecific, HasBranch, HasDependencyCheck, SoftDeletes;


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */

    protected $keyType = 'string'; // Set key type to string
    public $incrementing = false; // Disable auto-incrementing

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            $model->id = (string) new Ulid(); // Generate ULID
        });
    }
    protected $fillable = [
        'quantity',
        'unit',
        'symbol',
        'branch_id',
        'is_system',
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
        'is_system' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
        'parent_id' => 'string',
    ];

    public static function defaultQuantity(): array
    {
        return [
            [
            'quantity' => 'Count',
            'unit'       => "Pcs",
            'symbol'     => "ea",
            'is_system'  => true,
            ],
            [
                'quantity' => 'Length',
                'unit'       => "Centimetre",
                'symbol'     => "cm",
                'is_system'  => true,
            ],
            [
                'quantity' => 'Area',
                'unit'       => "SquareCentimetre",
                'symbol'     => "cm2",
                'is_system'  => true,
            ],
            [
                'quantity' => 'Weight',
                'unit'       => "Gram",
                'symbol'     => "g",
                'is_system'  => true,
            ],

            [
                'quantity' => 'Volume',
                'unit'       => "Millilitre",
                'symbol'     => "ml",
                'is_system'  => true,
            ],

            [
                'quantity' => 'Temperature',
                'unit'       => "Celsius",
                'symbol'     => "°C",
                'is_system'  => true,
            ],
        ];
    }
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function measures(): HasMany
    {
        return $this->hasMany(UnitMeasure::class, 'quantity_id');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'measures' => [
                'model' => 'unit measures',
                'message' => 'This quantity is used in unit measures'
            ]
        ];
    }
}
