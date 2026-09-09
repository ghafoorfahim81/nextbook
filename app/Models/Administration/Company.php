<?php

namespace App\Models\Administration;

use App\Models\User;
use App\Traits\HasDependencyCheck;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
    use HasFactory, HasUserAuditable, HasUserTracking, HasUlids, HasDependencyCheck;


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
        // Read in the company's own calendar: month 10 is Jadi for a Jalali
        // company, October for a Gregorian one.
        'fiscal_year_start_month',
        'fiscal_year_start_day',
        'working_style',
        'business_type',
        'locale',
        'currency_id',
        'costing_method',
        'email',
        'website',
        'invoice_description',
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
            'fiscal_year_start_month' => 'integer',
            'fiscal_year_start_day' => 'integer',
            'working_style' => WorkingStyle::class,
            'business_type' => BusinessType::class,
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
