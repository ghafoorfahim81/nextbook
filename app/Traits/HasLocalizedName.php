<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\App;

trait HasLocalizedName
{
    public function name(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->{'name_'.App::getLocale()}
        );
    }
}
