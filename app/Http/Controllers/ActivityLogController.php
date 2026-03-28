<?php

namespace App\Http\Controllers;

use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'search' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'module' => ['nullable', 'string'],
            'event_type' => ['nullable', 'string'],
            'user_id' => ['nullable', 'string'],
            'branch_id' => ['nullable', 'string'],
            'reference_type' => ['nullable', 'string'],
            'reference_id' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

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
            ->betweenDates(
                $validated['from'] ?? null,
                isset($validated['to']) ? $validated['to'] . ' 23:59:59' : null,
            )
            ->forModule($validated['module'] ?? null)
            ->forEventType($validated['event_type'] ?? null)
            ->forUser($validated['user_id'] ?? null)
            ->forBranch($validated['branch_id'] ?? null)
            ->forReference(
                $validated['reference_type'] ?? null,
                $validated['reference_id'] ?? null,
            )
            ->orderByDesc('created_at');

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
                'users' => User::query()
                    ->whereNull('deleted_at')
                    ->orderBy('name')
                    ->get(['id', 'name']),
            ],
        ]);
    }

    public function show(Request $request, ActivityLog $activityLog)
    {
        $activityLog->load(['user:id,name,email', 'branch:id,name']);

        if ($request->expectsJson()) {
            return ActivityLogResource::make($activityLog);
        }

        return Inertia::render('ActivityLogs/Show', [
            'log' => ActivityLogResource::make($activityLog)->resolve(),
        ]);
    }
}
