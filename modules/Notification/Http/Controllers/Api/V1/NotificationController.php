<?php

namespace Modules\Notification\Http\Controllers\Api\V1;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Routing\Controller;
use Modules\Core\Support\ApiPagination;
use Modules\Notification\Http\Resources\NotificationResource;

/**
 * The in-app inbox. Every customer message used to be an email, which a mobile
 * app has no way to read.
 */
class NotificationController extends Controller
{
    /**
     * GET /api/v1/notifications — newest first, unread count in `meta`.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $user = $request->user();

        $notifications = $user->notifications()
            ->latest()
            ->paginate(
                perPage: ApiPagination::perPage($request),
                page: ApiPagination::page($request),
            );

        // `unread` sits beside `meta`, not inside it: `meta` is the API-wide
        // pagination envelope and must keep exactly its four keys.
        return NotificationResource::collection($notifications->getCollection())
            ->additional([
                'unread' => $user->unreadNotifications()->count(),
                'meta' => ApiPagination::meta($notifications),
            ]);
    }

    /**
     * POST /api/v1/notifications/{id}/read
     */
    public function read(Request $request, string $id): JsonResponse
    {
        // Scoped to the caller's own notifications: an id from another user's
        // inbox must 404, never mark their message read.
        $notification = $request->user()->notifications()->whereKey($id)->first();

        abort_if($notification === null, 404);

        $notification->markAsRead();

        return response()->json(['data' => ['status' => 'read']]);
    }

    /**
     * POST /api/v1/notifications/read-all
     */
    public function readAll(Request $request): JsonResponse
    {
        $count = $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['data' => ['marked_read' => $count]]);
    }
}
