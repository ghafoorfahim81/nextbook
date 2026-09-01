<?php

namespace App\Http\Controllers;

use App\Enums\NotificationCategory;
use App\Http\Resources\NotificationResource;
use App\Models\Notification;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    private const TABS = ['all', 'unread', 'read', 'favorites', 'archived'];

    public function __construct(
        private readonly NotificationService $notificationService,
    ) {
    }

    public function index(Request $request): Response
    {
        $user = $request->user();

        $tab = $request->string('tab', 'all')->value();
        $tab = in_array($tab, self::TABS, true) ? $tab : 'all';

        $category = $request->string('category')->value() ?: null;
        $search = trim((string) $request->string('search')->value()) ?: null;
        $perPage = (int) $request->input('perPage', recordsPerPage());

        $notifications = $this->baseQuery($user)
            ->when(
                $tab === 'archived',
                fn (Builder $query) => $query->whereNotNull('archived_at'),
                fn (Builder $query) => $query->whereNull('archived_at'),
            )
            ->when($tab === 'unread', fn (Builder $query) => $query->where('is_read', false))
            ->when($tab === 'read', fn (Builder $query) => $query->where('is_read', true))
            ->when($tab === 'favorites', fn (Builder $query) => $query->whereNotNull('favorited_at'))
            ->when($category, fn (Builder $query) => $this->scopeToCategory($query, $category))
            ->when($search, fn (Builder $query) => $query->where(fn (Builder $inner) => $inner
                ->where('title', 'ilike', "%{$search}%")
                ->orWhere('message', 'ilike', "%{$search}%")))
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('Notifications/Index', [
            'notifications' => NotificationResource::collection($notifications),
            'summary' => $this->summary($user, $category),
            'filters' => [
                'tab' => $tab,
                'category' => $category,
                'search' => $search,
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
        $this->authorizeOwnership($request, $notification);

        $notification->forceFill(['is_read' => true])->save();

        return $this->respond($request, $notification);
    }

    public function markAsUnread(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->forceFill(['is_read' => false])->save();

        return $this->respond($request, $notification);
    }

    public function toggleFavorite(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->forceFill([
            'favorited_at' => $notification->favorited_at ? null : now(),
        ])->save();

        return $this->respond($request, $notification);
    }

    public function archive(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->forceFill(['archived_at' => now()])->save();

        return $this->respond($request, $notification);
    }

    public function unarchive(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->forceFill(['archived_at' => null])->save();

        return $this->respond($request, $notification);
    }

    public function destroy(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        $this->authorizeOwnership($request, $notification);

        $notification->delete();

        return $this->respond($request, $notification);
    }

    public function markAllAsRead(Request $request): JsonResponse|RedirectResponse
    {
        $this->baseQuery($request->user())
            ->whereNull('archived_at')
            ->where('is_read', false)
            ->update(['is_read' => true]);

        if ($request->expectsJson()) {
            return response()->json(
                $this->notificationService->getNotificationCenter($request->user())
            );
        }

        return back()->with('success', 'All notifications marked as read.');
    }

    public function bulk(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'action' => 'required|in:read,unread,favorite,unfavorite,archive,unarchive,delete',
            'ids' => 'required|array|min:1',
            'ids.*' => 'string',
        ]);

        $query = $this->baseQuery($request->user())->whereIn('id', $data['ids']);

        match ($data['action']) {
            'read' => $query->update(['is_read' => true]),
            'unread' => $query->update(['is_read' => false]),
            'favorite' => $query->update(['favorited_at' => now()]),
            'unfavorite' => $query->update(['favorited_at' => null]),
            'archive' => $query->update(['archived_at' => now()]),
            'unarchive' => $query->update(['archived_at' => null]),
            'delete' => $query->delete(),
        };

        if ($request->expectsJson()) {
            return response()->json([
                'notification_center' => $this->notificationService->getNotificationCenter($request->user()),
            ]);
        }

        return back()->with('success', 'Notifications updated.');
    }

    private function baseQuery(User $user): Builder
    {
        return Notification::query()->where('user_id', $user->id);
    }

    private function scopeToCategory(Builder $query, string $category): Builder
    {
        $case = NotificationCategory::tryFrom($category);

        if (! $case) {
            return $query;
        }

        return $case === NotificationCategory::System
            ? $query->whereNotIn('type', NotificationCategory::mappedTypes())
            : $query->whereIn('type', $case->types());
    }

    private function authorizeOwnership(Request $request, Notification $notification): void
    {
        abort_unless($notification->user_id === $request->user()->id, 403);
    }

    private function respond(Request $request, Notification $notification): JsonResponse|RedirectResponse
    {
        if ($request->expectsJson()) {
            return response()->json([
                'notification' => (new NotificationResource($notification->fresh() ?? $notification))->resolve(),
                'notification_center' => $this->notificationService->getNotificationCenter($request->user()),
            ]);
        }

        return back()->with('success', 'Notification updated.');
    }

    /**
     * Counts that drive the command-centre header, module rail and tab badges.
     *
     * - `categories` (module rail) is always the full picture, ignoring the
     *   selected module so the rail keeps showing where the noise is.
     * - the header stat cards and `tabs` follow the selected module, so
     *   switching to an empty module shows zeros instead of global totals.
     *
     * @return array<string, mixed>
     */
    private function summary(User $user, ?string $category = null): array
    {
        $categories = [];
        foreach (NotificationCategory::cases() as $case) {
            $categories[$case->value] = ['total' => 0, 'unread' => 0];
        }

        $rows = $this->baseQuery($user)
            ->whereNull('archived_at')
            ->selectRaw('type, count(*) as total, count(*) filter (where is_read = false) as unread')
            ->groupBy('type')
            ->get();

        foreach ($rows as $row) {
            $key = NotificationCategory::forType($row->type)->value;
            $categories[$key]['total'] += (int) $row->total;
            $categories[$key]['unread'] += (int) $row->unread;
        }

        $scoped = fn (): Builder => $this->baseQuery($user)
            ->when($category, fn (Builder $query) => $this->scopeToCategory($query, $category));

        $active = fn (): Builder => $scoped()->whereNull('archived_at');

        return [
            'total' => $active()->count(),
            'unread' => $active()->where('is_read', false)->count(),
            'today' => $active()->where('created_at', '>=', now()->startOfDay())->count(),
            'week' => $active()->where('created_at', '>=', now()->startOfWeek())->count(),
            'favorites' => $active()->whereNotNull('favorited_at')->count(),
            'archived' => $scoped()->whereNotNull('archived_at')->count(),
            'categories' => $categories,
            'tabs' => [
                'all' => $active()->count(),
                'unread' => $active()->where('is_read', false)->count(),
                'read' => $active()->where('is_read', true)->count(),
                'favorites' => $active()->whereNotNull('favorited_at')->count(),
                'archived' => $scoped()->whereNotNull('archived_at')->count(),
            ],
        ];
    }
}
