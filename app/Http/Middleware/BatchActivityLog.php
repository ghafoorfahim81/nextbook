<?php

namespace App\Http\Middleware;

use App\Services\ActivityLogService;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Turns everything one request saves into a single activity log entry.
 *
 * Saving a transfer, an item or a purchase touches several records, and each
 * used to write its own row, so one click showed up as three to five entries.
 * Entries are collected for the length of the request and written as one log
 * — headed by the record the user acted on, with the rest attached.
 */
class BatchActivityLog
{
    public function __construct(
        protected ActivityLogService $activityLog
    ) {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $this->activityLog->beginBatch();

        $routeModel = $this->routeModel($request);
        $before = $this->snapshotBefore($request, $routeModel);

        try {
            $response = $next($request);
        } finally {
            // Exceptions become responses further in, but flush on any exit so
            // committed work is never left unlogged.
            try {
                $this->activityLog->flushBatch(
                    $request->route()?->getName(),
                    $routeModel,
                    $before,
                );
            } catch (\Throwable $exception) {
                // The audit trail must never break the action it records.
                report($exception);
            }
        }

        return $response;
    }

    /**
     * The edited record's child data (opening, lines, variants) as it was
     * before this request, so the log can show what an edit replaced.
     */
    protected function snapshotBefore(Request $request, ?Model $routeModel): ?array
    {
        if (! $routeModel || $request->isMethod('GET') || $request->isMethod('HEAD')) {
            return null;
        }

        // A delete keeps its own snapshot in old_values already.
        if (str_ends_with((string) $request->route()?->getName(), '.destroy')) {
            return null;
        }

        try {
            // A separate copy: loading relations onto the bound model would
            // hand the controller stale children after it replaces them.
            $copy = $routeModel->fresh();

            return $copy ? $this->activityLog->relationSnapshot($copy) : null;
        } catch (\Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** The first record bound from the URL, e.g. the {item} in /items/{item}. */
    protected function routeModel(Request $request): ?Model
    {
        foreach ($request->route()?->parameters() ?? [] as $parameter) {
            if ($parameter instanceof Model) {
                return $parameter;
            }
        }

        return null;
    }
}
