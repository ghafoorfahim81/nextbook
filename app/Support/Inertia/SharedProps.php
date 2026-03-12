<?php

namespace App\Support\Inertia;

use Illuminate\Http\Request;

final class SharedProps
{
    /**
     * @return array<string, mixed>
     */
    public static function make(Request $request): array
    {
        return [
            ...CoreShared::make($request),
            ...LookupShared::make($request),
            ...DomainShared::make($request),
        ];
    }
}
