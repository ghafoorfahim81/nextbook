<?php

namespace App\Models\Inventory;

use App\Traits\CompanySpecific;
use App\Traits\HasCompany;
use App\Traits\HasDependencyCheck;
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
use Illuminate\Support\Str;

/**
 * One sellable product: the thing a customer actually chooses.
 *
 * Every item has at least one variant, even a bag of salt, so that
 * variant_id can be relied on everywhere downstream and barcode scanning and
 * price lookup have a single code path.
 */
class ItemVariant extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasSearch,
        HasSorting, HasCompany, CompanySpecific, HasDependencyCheck, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'item_id',
        'attributes',
        'variant_key',
        'name',
        'sku',
        'barcode',
        'sale_price',
        'purchase_price',
        'avg_cost',
        'minimum_stock',
        'maximum_stock',
        'weight',
        'sort_order',
        'is_default',
        'is_active',
        'company_id',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'item_id' => 'string',
            'company_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
            'attributes' => 'array',
            'sale_price' => 'decimal:4',
            'purchase_price' => 'decimal:4',
            'avg_cost' => \App\Casts\StockCost::class,
            'minimum_stock' => 'decimal:4',
            'maximum_stock' => 'decimal:4',
            'weight' => 'decimal:4',
            'sort_order' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    protected static function searchableColumns(): array
    {
        return ['name', 'sku', 'barcode', 'item.name', 'item.code'];
    }

    protected static function booted(): void
    {
        // The key is derived, never supplied by the caller, so two users
        // creating "16GB / Black" on the same day collide instead of silently
        // splitting the stock between two rows.
        //
        // getAttribute(), not $variant->attributes: inside the model that
        // property is Eloquent's own storage for every column, so the magic
        // getter never runs and the key would be built from the whole row.
        static::saving(function (ItemVariant $variant) {
            $variant->variant_key = static::makeKey(
                (array) ($variant->getAttribute('attributes') ?? [])
            );
        });
    }

    /**
     * Canonical fingerprint of a set of variant attributes.
     *
     * Sorted and lower-cased so {"ram":"16GB","color":"Black"} and
     * {"color":"black","ram":"16gb"} produce the same key — they mean the same
     * product, but are different JSONB text and would otherwise both insert.
     */
    public static function makeKey(array $attributes): string
    {
        if (empty($attributes)) {
            return 'default';
        }

        $normalized = [];

        foreach ($attributes as $name => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            $normalized[Str::lower(trim((string) $name))] = Str::lower(trim((string) $value));
        }

        if (empty($normalized)) {
            return 'default';
        }

        ksort($normalized);

        return collect($normalized)
            ->map(fn ($value, $name) => "{$name}={$value}")
            ->implode('|');
    }

    /**
     * Human label built from the attribute values: "16GB / 512GB / Silver".
     */
    public function displayName(): string
    {
        if (filled($this->name)) {
            return $this->name;
        }

        // Same trap as above: getAttribute(), not $this->attributes.
        $values = collect($this->getAttribute('attributes') ?? [])->filter()->values();

        return $values->isEmpty()
            ? (string) $this->item?->name
            : $values->implode(' / ');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function lots(): HasMany
    {
        return $this->hasMany(StockLot::class, 'variant_id');
    }

    public function pieces(): HasMany
    {
        return $this->hasMany(StockPiece::class, 'variant_id');
    }

    public function stockBalances(): HasMany
    {
        return $this->hasMany(StockBalance::class, 'variant_id');
    }

    /**
     * Tables whose lines point at a variant. Any row here means the variant
     * has been used in a document, so its identity can no longer change.
     */
    private const LINE_TABLES = [
        'purchase_items', 'purchase_return_items', 'purchase_order_items', 'purchase_quotation_items',
        'sale_items', 'sale_return_items', 'sale_order_items', 'sale_quotation_items',
        'item_transfer_items', 'stock_adjustment_items', 'landed_cost_items',
    ];

    /**
     * Whether anything other than the opening balance has touched this variant:
     * a document line or a stock movement. Once it has, deleting or re-keying the
     * variant would orphan those lines or move stock to a different product.
     */
    public function hasTransactions(): bool
    {
        if (! $this->exists) {
            return false;
        }

        foreach (self::LINE_TABLES as $table) {
            if (\Illuminate\Support\Facades\DB::table($table)->where('variant_id', $this->id)->exists()) {
                return true;
            }
        }

        return \Illuminate\Support\Facades\DB::table('stock_movements')
            ->where('variant_id', $this->id)
            ->where('source', '!=', \App\Enums\StockSourceType::OPENING->value)
            ->exists();
    }

    public function onHand(): float
    {
        return (float) $this->stockBalances()->sum('quantity');
    }

    protected function getRelationships(): array
    {
        return [
            'stock_balances' => [
                'model' => 'stock_balances',
                'message' => 'This variant has stock balances',
            ],
            'sale_items' => [
                'model' => 'sale_items',
                'message' => 'This variant has been sold',
            ],
        ];
    }
}
