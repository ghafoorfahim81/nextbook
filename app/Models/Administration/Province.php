<?php

namespace App\Models\Administration;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Province extends Model
{
    use HasFactory, HasUlids;

    protected $fillable = ['country_id', 'name_en', 'name_fa'];

    protected $appends = ['localized_name'];

    public function country(): BelongsTo
    {
        return $this->belongsTo(Country::class);
    }

    public function getLocalizedNameAttribute(): string
    {
        return app()->getLocale() === 'fa' ? $this->name_fa : $this->name_en;
    }
}
