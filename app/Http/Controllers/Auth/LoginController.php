<?php

namespace App\Http\Controllers\Auth;

use App\Services\ActivityLogService;
use Illuminate\Support\Facades\Cache;
use Laravel\Fortify\Http\Controllers\AuthenticatedSessionController;
use Laravel\Fortify\Http\Requests\LoginRequest;

class LoginController extends AuthenticatedSessionController
{
    public function store(LoginRequest $request)
    {
        $response = parent::store($request);

        Cache::flush();

        if ($request->user()) {
            app(ActivityLogService::class)->logAction(
                eventType: 'login',
                reference: $request->user(),
                module: 'user',
                description: "User {$request->user()->name} logged in.",
                metadata: [
                    'action' => 'login',
                ],
            );
        }

        return $response;
    }
}
