<?php

namespace App\Models\Administration;

use App\Traits\BranchSpecific;
use App\Traits\HasBranch;
use App\Traits\HasCache;
use App\Traits\HasDependencyCheck;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class LandedCostCategory extends Model
{
    use HasFactory, HasUlids, HasSearch, HasSorting, HasUserTracking, HasUserAuditable,
        HasCache, HasDependencyCheck, BranchSpecific, HasBranch, SoftDeletes;

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'name',
        'local_name',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'local_name' => 'string',
            'branch_id' => 'string',
            'created_by' => 'string',
            'updated_by' => 'string',
        ];
    }

    protected static function searchableColumns(): array
    {
        return [
            'name',
            'local_name',
            'remark',
        ];
    }

    /**
     * Every payload carries the label the UI should print, so callers never have
     * to repeat the locale check.
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

    protected function getRelationships(): array
    {
        return [];
    }

    /**
     * Landed cost categories installed for every new branch.
     *
     * `name` is the English label; `local_name` is the Dari/Persian label the
     * UI falls back to under the fa/ps locales.
     */
    public static function defaultCategories(): array
    {
        return [
            ['name' => 'Freight & Transport', 'local_name' => 'کرایه و ترانسپورت', 'remark' => null],
            ['name' => 'Customs & Government', 'local_name' => 'گمرک و محصولات دولتی', 'remark' => null],
            ['name' => 'Handling & Port', 'local_name' => 'تخلیه، بارگیری و بندر', 'remark' => null],
            ['name' => 'Storage & Warehousing', 'local_name' => 'ذخیره و گدام', 'remark' => null],
            ['name' => 'Agent & Intermediary', 'local_name' => 'کمیشن‌کاری و نمایندگی', 'remark' => null],
            ['name' => 'Insurance & Risk', 'local_name' => 'بیمه و خطرات', 'remark' => null],
            ['name' => 'Financial & Currency', 'local_name' => 'مصارف مالی و تبادله اسعار', 'remark' => null],
            ['name' => 'Compliance & Certification', 'local_name' => 'تاییدیه‌ها و اسناد قانونی', 'remark' => null],
            ['name' => 'Other', 'local_name' => 'متفرقه', 'remark' => null],
        ];
    }
}
