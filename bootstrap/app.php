<?php

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetActiveBranch;
use App\Http\Middleware\SetActiveCompany;
use App\Http\Middleware\SetDatabaseCompanyContext;
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
            // Resolves the company from the user's membership. Must precede
            // SetActiveBranch, which validates its branch against it.
            SetActiveCompany::class,
            // Hands that company to Postgres, where the row level security
            // policies read it. Must run after SetActiveCompany and before
            // anything that reads a protected table.
            SetDatabaseCompanyContext::class,
            // Used by HandleInertiaRequests to compute branch-scoped data.
            SetActiveBranch::class,
            HandleInertiaRequests::class,
            // Refuses a write from a page opened in another company (a tab left
            // open across a company switch). After HandleInertiaRequests, so its
            // redirect passes back out through Inertia and becomes a 303.
            \App\Http\Middleware\RejectStaleCompanyContext::class,
            // One activity log entry per request, not one per saved row.
            \App\Http\Middleware\BatchActivityLog::class,
        ]);

        // Route model binding reads a protected table, so Postgres has to know
        // the company first; appended to the group, these would run after
        // SubstituteBindings and every bound model would come back as a 404.
        foreach ([SetActiveCompany::class, SetDatabaseCompanyContext::class, SetActiveBranch::class] as $contextMiddleware) {
            $middleware->prependToPriorityList(\Illuminate\Routing\Middleware\SubstituteBindings::class, $contextMiddleware);
        }
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
