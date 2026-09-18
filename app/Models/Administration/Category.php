<?php

namespace App\Models\Administration;

use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasCache;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;
use App\Traits\HasBranch;
use App\Traits\BranchSpecific;
class Category extends Model
{
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasCache, HasSearch, HasSorting, HasDependencyCheck, BranchSpecific, HasBranch, SoftDeletes;

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'categories';

    /**
     * The primary key for the model.
     *
     * @var string
     */
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * The "type" of the auto-incrementing ID.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'local_name',
        'parent_id',
        'remark',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'id' => 'string',
        'local_name' => 'string',
        'parent_id' => 'string',
        'is_active' => 'boolean',
        'created_by' => 'string',
        'updated_by' => 'string',
    ];


    /**
     * Columns used for searchable queries.
     *
     * @return array
     */
    protected static function searchableColumns(): array
    {
        return [
            'name',
            'local_name',
            'parent.name',
            'parent.local_name',
        ];
    }

    /**
     * Every category payload carries the label the UI should print, so callers
     * never have to repeat the locale check.
     */
    protected $appends = ['localized_name'];

    /**
     * The category name for the active locale: the local name under the Persian
     * and Pashto locales, the English name everywhere else (and whenever no
     * local name was captured).
     */
    public function getLocalizedNameAttribute(): string
    {
        $useLocal = in_array(app()->getLocale(), ['fa', 'ps', 'pa'], true);

        return $useLocal && filled($this->local_name)
            ? (string) $this->local_name
            : (string) $this->name;
    }

    /**
     * Get the parent category.
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Category::class, 'parent_id');
    }

    /**
     * Get the child categories.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Category::class, 'parent_id');
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'items' => [
                'model' => 'items',
                'message' => 'This category is used in items'
            ],
            'children' => [
                'model' => 'subcategories',
                'message' => 'This category has subcategories'
            ]
        ];
    }

    /**
     * Check if category is deleted
     */
    public function isDeleted(): bool
    {
        return !is_null($this->deleted_at);
    }

    /**
     * Relationship to items that use this category
     */
    public function items()
    {
        return $this->hasMany(\App\Models\Inventory\Item::class, 'category_id');
    }

    /**
     * Item categories provisioned for every new branch and installed by the
     * category seeder.
     *
     * `name` is the English label; `local_name` is the Dari/Persian label the
     * UI falls back to under the fa/ps locales.
     */
    public static function defaultCategories(): array
    {
        return [
            ['name' => 'General', 'local_name' => 'عمومی', 'remark' => 'Catch-all for uncategorised items'],
            ['name' => 'Food & Grocery', 'local_name' => 'مواد غذایی و خواربار', 'remark' => null],
            ['name' => 'Beverages', 'local_name' => 'نوشیدنی‌ها', 'remark' => null],
            ['name' => 'Dairy & Eggs', 'local_name' => 'لبنیات و تخم مرغ', 'remark' => null],
            ['name' => 'Bakery & Confectionery', 'local_name' => 'نانوایی و شیرینی‌پزی', 'remark' => null],
            ['name' => 'Fruits & Vegetables', 'local_name' => 'میوه و سبزیجات', 'remark' => null],
            ['name' => 'Meat & Poultry', 'local_name' => 'گوشت و مرغ', 'remark' => null],
            ['name' => 'Grains & Pulses', 'local_name' => 'غله‌جات و حبوبات', 'remark' => null],
            ['name' => 'Cooking Oil & Ghee', 'local_name' => 'روغن پخت و پز و روغن جامد', 'remark' => null],
            ['name' => 'Spices & Condiments', 'local_name' => 'ادویه‌جات و چاشنی‌ها', 'remark' => null],
            ['name' => 'Tea & Coffee', 'local_name' => 'چای و قهوه', 'remark' => null],
            ['name' => 'Household & Cleaning', 'local_name' => 'لوازم خانه و نظافت', 'remark' => null],
            ['name' => 'Personal Care & Hygiene', 'local_name' => 'بهداشت و مراقبت شخصی', 'remark' => null],
            ['name' => 'Baby & Kids', 'local_name' => 'کودک و نوزاد', 'remark' => null],
            ['name' => 'Pharmaceuticals', 'local_name' => 'ادویه و داروها', 'remark' => null],
            ['name' => 'Medical Supplies', 'local_name' => 'لوازم طبی', 'remark' => null],
            ['name' => 'Clothing & Apparel', 'local_name' => 'پوشاک و لباس', 'remark' => null],
            ['name' => 'Footwear', 'local_name' => 'کفش و پاپوش', 'remark' => null],
            ['name' => 'Textiles & Fabrics', 'local_name' => 'منسوجات و پارچه', 'remark' => null],
            ['name' => 'Jewellery & Accessories', 'local_name' => 'جواهرات و زیورآلات', 'remark' => null],
            ['name' => 'Electronics & Appliances', 'local_name' => 'وسایل الکترونیکی و برقی', 'remark' => null],
            ['name' => 'Mobile & Accessories', 'local_name' => 'موبایل و لوازم جانبی', 'remark' => null],
            ['name' => 'Computers & IT', 'local_name' => 'کمپیوتر و تکنالوژی معلوماتی', 'remark' => null],
            ['name' => 'Stationery & Office Supplies', 'local_name' => 'قرطاسیه و لوازم دفتر', 'remark' => null],
            ['name' => 'Furniture & Fixtures', 'local_name' => 'اثاثیه و ملحقات', 'remark' => null],
            ['name' => 'Kitchenware & Utensils', 'local_name' => 'لوازم آشپزخانه و ظروف', 'remark' => null],
            ['name' => 'Hardware & Tools', 'local_name' => 'آهن‌آلات و ابزار', 'remark' => null],
            ['name' => 'Construction Materials', 'local_name' => 'مواد ساختمانی', 'remark' => null],
            ['name' => 'Paint & Chemicals', 'local_name' => 'رنگ و مواد کیمیاوی', 'remark' => null],
            ['name' => 'Plumbing & Sanitary', 'local_name' => 'لوله‌کشی و سنیتری', 'remark' => null],
            ['name' => 'Electrical Supplies', 'local_name' => 'لوازم برقی و سیم‌کشی', 'remark' => null],
            ['name' => 'Auto Parts & Accessories', 'local_name' => 'پرزه‌جات و لوازم موتر', 'remark' => null],
            ['name' => 'Tyres & Batteries', 'local_name' => 'تایر و بطری', 'remark' => null],
            ['name' => 'Fuel & Lubricants', 'local_name' => 'تیل و روغنیات', 'remark' => null],
            ['name' => 'Agriculture & Livestock', 'local_name' => 'زراعت و مالداری', 'remark' => null],
            ['name' => 'Packaging Materials', 'local_name' => 'مواد بسته‌بندی', 'remark' => null],
            ['name' => 'Sports & Outdoor', 'local_name' => 'ورزشی و تفریحی', 'remark' => null],
            ['name' => 'Toys & Games', 'local_name' => 'اسباب‌بازی و بازی‌ها', 'remark' => null],
            ['name' => 'Books & Media', 'local_name' => 'کتاب و رسانه', 'remark' => null],
            ['name' => 'Services', 'local_name' => 'خدمات', 'remark' => null],
        ];
    }
}
