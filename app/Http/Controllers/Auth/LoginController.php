<?php

namespace App\Http\Controllers\Auth;

use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\LoginRequest;

class LoginController extends AuthenticatedSessionController
{
    public function store(LoginRequest $request)
    {
        $response = parent::store($request);

        Cache::flush();

        return $response;
    }
}
