<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\NotificationResource;
use App\Traits\ApiResponses;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Screen 18 — the notification feed, served from Laravel's own
 * `notifications` table.
 */
class NotificationController extends Controller
{
    use ApiResponses;

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'filter' => ['nullable', 'in:all,unread'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $user = $request->user();

        $query = ($validated['filter'] ?? 'all') === 'unread'
            ? $user->unreadNotifications()
            : $user->notifications();

        $notifications = $query->paginate($validated['per_page'] ?? 20);

        return $this->paginated(NotificationResource::collection($notifications));
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return $this->ok(['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function markRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return $this->fail('Notification not found.', 404);
        }

        $notification->markAsRead();

        return $this->ok(new NotificationResource($notification->fresh()), 'Marked as read.');
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return $this->ok(null, 'All notifications marked as read.');
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $deleted = $request->user()->notifications()->whereKey($id)->delete();

        if (! $deleted) {
            return $this->fail('Notification not found.', 404);
        }

        return $this->ok(null, 'Notification removed.');
    }
}
