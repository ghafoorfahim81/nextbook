<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetActiveBranch;
use App\Http\Middleware\SetLocale;
use Inertia\Inertia;
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            // Must run before Inertia shared props so `locale` and `direction` are correct.
            SetLocale::class,
            // Used by HandleInertiaRequests to compute branch-scoped data.
            SetActiveBranch::class,
            HandleInertiaRequests::class,
            // One activity log entry per request, not one per saved row.
            \App\Http\Middleware\BatchActivityLog::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (AuthorizationException $e, $request) {
            // Laravel fills in its own untranslated English default when a
            // policy denies without a reason; show ours in the user's language.
            $message = $e->getMessage();
            if ($message === '' || $message === 'This action is unauthorized.') {
                $message = __('messages.errors.unauthorized');
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 403);
            }

            return Inertia::render('Errors/Forbidden', [
                'status' => 403,
                'message' => $message,
            ])->toResponse($request)->setStatusCode(403);
        });
    })->create();
