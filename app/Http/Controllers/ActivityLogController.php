<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\DateConversionService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        // The route had no permission at all, so any signed-in user — a
        // view-only clerk included — could read who did what, with amounts
        // and IP addresses.
        $this->authorize('activity_logs.view_any');

        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            // Strings, not 'date': under the Jalali calendar the picker sends
            // e.g. 1405-02-31, a real Jalali day that the Gregorian 'date' rule
            // rejects. Both are converted to Gregorian before querying.
            'from' => ['nullable', 'string', 'max:20'],
            'to' => ['nullable', 'string', 'max:20'],
            'module' => ['nullable', 'string'],
            'event_type' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'string'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $fromDate = $this->gregorianDate($validated['from'] ?? null);
        $toDate = $this->gregorianDate($validated['to'] ?? null);

        $query = ActivityLog::query()
            ->with(['user:id,name', 'branch:id,name'])
            ->when($validated['search'] ?? null, function ($builder, string $search) {
                $builder->where(function ($subQuery) use ($search) {
                    $subQuery
                        ->where('description', 'ilike', "%{$search}%")
                        ->orWhere('event_type', 'ilike', "%{$search}%")
                        ->orWhere('module', 'ilike', "%{$search}%")
                        ->orWhere('reference_type', 'ilike', "%{$search}%")
                        ->orWhere('reference_id', 'ilike', "%{$search}%")
                        ->orWhereHas('user', fn ($userQuery) => $userQuery->where('name', 'ilike', "%{$search}%"));
                });
            })
            // Stored timestamps are Gregorian; a Jalali 1405-07-06 compared as
            // text matched nothing (or everything).
            ->betweenDates(
                $fromDate,
                $toDate ? $toDate . ' 23:59:59' : null,
            )
            ->forModule($validated['module'] ?? null)
            ->forEventType($validated['event_type'] ?? null)
            ->forUser($validated['user_id'] ?? null)
            ->forBranch($validated['branch_id'] ?? null)
            ->forReference(
                $validated['reference_type'] ?? null,
                $validated['reference_id'] ?? null,
            )
            // created_at is stored to the second, so events from one action
            // tie. ULIDs carry millisecond time, so the id keeps them in the
            // order they happened.
            ->orderByDesc('created_at')
            ->orderByDesc('id');

        $logs = $query
            ->paginate($validated['per_page'] ?? 25)
            ->withQueryString();

        if ($request->expectsJson()) {
            return ActivityLogResource::collection($logs);
        }

        return Inertia::render('ActivityLogs/Index', [
            'logs' => ActivityLogResource::collection($logs),
            'filters' => [
                'search' => $validated['search'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'module' => $validated['module'] ?? null,
                'event_type' => $validated['event_type'] ?? null,
                'user_id' => $validated['user_id'] ?? null,
                'branch_id' => $validated['branch_id'] ?? null,
                'reference_type' => $validated['reference_type'] ?? null,
                'reference_id' => $validated['reference_id'] ?? null,
                'per_page' => (int) ($validated['per_page'] ?? 25),
            ],
            'filterOptions' => [
                'modules' => ActivityLog::query()
                    ->select('module')
                    ->distinct()
                    ->orderBy('module')
                    ->pluck('module')
                    ->values(),
                'event_types' => ActivityLog::query()
                    ->select('event_type')
                    ->distinct()
                    ->orderBy('event_type')
                    ->pluck('event_type')
                    ->values(),
                // Only people who appear in this branch's log. Listing every
                // user in the database leaked other tenants' staff names.
                'users' => User::query()
                    ->whereIn('id', ActivityLog::query()->select('user_id')->whereNotNull('user_id')->distinct())
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    /**
     * A filter date in either calendar as Y-m-d Gregorian, or null when it
     * cannot be read (so a malformed value drops the bound instead of erroring).
     */
    protected function gregorianDate(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        try {
            $date = app(DateConversionService::class)->toGregorian(trim($value));
        } catch (\Throwable) {
            return null;
        }

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $date) ? $date : null;
    }

    public function show(Request $request, ActivityLog $activityLog)
    {
        $this->authorize('activity_logs.view');

        $activityLog->load(['user:id,name,email', 'branch:id,name']);

        if ($request->expectsJson()) {
            return ActivityLogResource::make($activityLog);
        }

        return Inertia::render('ActivityLogs/Show', [
            'log' => ActivityLogResource::make($activityLog)->resolve(),
        ]);
    }
}
