<?php

namespace App\Models\Administration;

use App\Models\User;
use App\Traits\HasDependencyCheck;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Traits\HasSearch;
use App\Traits\HasSorting;
use App\Traits\HasUserAuditable;
use App\Traits\HasUserTracking;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasCache;
use App\Enums\CalendarType;
use App\Enums\BusinessType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;
use App\Enums\CostingMethod;

class Company extends Model
{
    // HasSearch backs Company::search(), which the company list page calls;
    // without it the page fails with an undefined method. SoftDeletes backs the
    // companies.restore route, which otherwise 500s on a model that was never
    // soft-deletable.
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasDependencyCheck, HasSearch, HasSorting, SoftDeletes;

    protected static function booted(): void
    {
        // CompanyContext::company() keeps the active company's model for the
        // rest of the request. A save through any other instance — the company
        // settings form, a test changing the calendar — would otherwise leave
        // that copy answering with the old values until the request ends.
        $forget = function (Company $company): void {
            if (app()->bound('active_company') && (string) app('active_company')?->getKey() === (string) $company->getKey()) {
                app()->forgetInstance('active_company');
            }
        };

        static::saved($forget);
        static::deleted($forget);
    }

    /**
     * The company's name in the given (or current) locale, falling back the
     * way the printed documents do.
     */
    public function localizedName(?string $locale = null): string
    {
        return match ($locale ?? app()->getLocale()) {
            'fa' => $this->name_fa ?: $this->name_en ?: $this->abbreviation ?: '',
            'ps' => $this->name_pa ?: $this->name_en ?: $this->abbreviation ?: '',
            default => $this->name_en ?: $this->abbreviation ?: $this->name_fa ?: $this->name_pa ?: '',
        };
    }

    protected static function searchableColumns(): array
    {
        return [
            'name_en',
            'name_fa',
            'name_pa',
            'abbreviation',
            'phone',
            'email',
            'city',
        ];
    }


    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name_en',
        'name_fa',
        'name_pa',
        'abbreviation',
        'address',
        'phone',
        'country',
        'city',
        'logo',
        'calendar_type',
        'working_style',
        'business_type',
        'preferences',
        'locale',
        'currency_id',
        'costing_method',
        'license_number',
        'license_issued_at',
        'license_expires_at',
        'email',
        'website',
        'invoice_description',
        'invoice_theme',
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
            'created_by' => 'string',
            'updated_by' => 'string',
            'calendar_type' => CalendarType::class,
            'working_style' => WorkingStyle::class,
            'business_type' => BusinessType::class,
            'preferences' => 'array',
            'license_issued_at' => 'date',
            'license_expires_at' => 'date',
            'locale' => Locale::class,
            'costing_method' => CostingMethod::class,
        ];
    }

    /**
     * Get all of the users for the company.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Branches operating under this company.
     */
    public function branches(): HasMany
    {
        return $this->hasMany(Branch::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * Get relationships configuration for dependency checking
     */
    protected function getRelationships(): array
    {
        return [
            'users' => [
                'model' => 'users',
                'message' => 'This company has users'
            ]
        ];
    }

    /**
     * Get the logo URL attribute.
     */
    public function getLogoUrlAttribute()
    {
        if ($this->logo) {
            return asset('storage/' . $this->logo);
        }
        return null;
    }
}
