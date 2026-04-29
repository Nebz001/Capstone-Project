<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * GET /api/notifications
     *
     * Supports `?filter=all|unread|read` and `?type=<notification_type>`.
     * Always scoped to the authenticated user.
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $filter = (string) $request->string('filter', 'all');
        $type = trim((string) $request->string('type', ''));

        $query = Notification::query()
            ->where('user_id', $user->id)
            ->orderByDesc('created_at');

        if ($filter === 'unread') {
            $query->unread();
        } elseif ($filter === 'read') {
            $query->read();
        }

        if ($type !== '') {
            $query->where('type', $type);
        }

        $notifications = $query->limit(100)->get();

        $unreadCount = (int) Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->count();

        $data = $notifications->map(fn (Notification $notification): array => [
            'id' => (int) $notification->id,
            'title' => (string) $notification->title,
            'message' => (string) $notification->body,
            'type' => (string) $notification->type,
            'link_url' => $notification->link_url,
            'read_at' => optional($notification->read_at)->toIso8601String(),
            'created_at' => optional($notification->created_at)->toIso8601String(),
        ])->values();

        return response()->json([
            'data' => $data,
            'meta' => [
                'unread_count' => $unreadCount,
            ],
        ]);
    }

    /**
     * PATCH /api/notifications/{notification}/read
     */
    public function markRead(Request $request, Notification $notification): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        if ((int) $notification->user_id !== (int) $user->id) {
            return response()->json(['message' => 'Resource not found.'], 404);
        }

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'message' => 'Notification marked as read.',
            'data' => [
                'id' => (int) $notification->id,
                'read_at' => optional($notification->read_at)->toIso8601String(),
            ],
        ]);
    }

    /**
     * PATCH /api/notifications/read-all
     */
    public function markAllRead(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'Unauthorized.'], 401);
        }

        $updated = Notification::query()
            ->where('user_id', $user->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'message' => 'All notifications marked as read.',
            'data' => [
                'updated_count' => (int) $updated,
            ],
        ]);
    }
}
