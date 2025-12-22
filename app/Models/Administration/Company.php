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
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Support\Facades\Storage;
use App\Traits\HasCache;
use App\Enums\CalendarType;
use App\Enums\BusinessType;
use App\Enums\Locale;
use App\Enums\WorkingStyle;

class Company extends Model
{
    use HasFactory, HasUserAuditable, HasUlids, HasDependencyCheck;


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
        'locale',
        'currency_id',
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
            'created_by' => 'integer',
            'updated_by' => 'integer',
            'calendar_type' => CalendarType::class,
            'working_style' => WorkingStyle::class,
            'business_type' => BusinessType::class,
            'locale' => Locale::class,
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
