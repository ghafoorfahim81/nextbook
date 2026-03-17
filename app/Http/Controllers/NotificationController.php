<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Services\NotificationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): Response
    {
        $filter = $request->string('filter', 'all')->value();
        $perPage = (int) $request->input('perPage', recordsPerPage());

        $notifications = Notification::query()
            ->where('user_id', $request->user()->id)
            ->when($filter === 'read', fn ($query) => $query->where('is_read', true))
            ->when($filter === 'unread', fn ($query) => $query->where('is_read', false))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationResource::collection($notifications),
            'filters' => [
                'filter' => $filter,
                'perPage' => $perPage,
            ],
        ]);
    }

    public function feed(Request $request): JsonResponse
    {
        $limit = min(20, max(5, (int) $request->input('limit', 8)));

        return response()->json(
            $this->notificationService->getNotificationCenter($request->user(), $limit)
        );
    }

    public function markAsRead(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        abort_unless($notification->user_id === $request->user()->id, 403);

        if (! $notification->is_read) {
            $notification->forceFill(['is_read' => true])->save();
        }

        if ($request->expectsJson()) {
            return response()->json([
                'notification' => (new NotificationResource($notification->fresh()))->resolve(),
                'notification_center' => $this->notificationService->getNotificationCenter($request->user()),
            ]);
        }

        return back()->with('success', 'Notification marked as read.');
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        Notification::query()
            ->where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json(
                $this->notificationService->getNotificationCenter($request->user())
            );
        }

        return back()->with('success', 'All notifications marked as read.');
    }
}
