<?php

namespace App\Http\Controllers\Api\Rider;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    /**
     * Get notifications for the authenticated rider.
     *
     * GET /api/rider/notifications
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $rider = $request->user();

            /*
            |--------------------------------------------------------------------------
            | AUTHENTICATION
            |--------------------------------------------------------------------------
            */

            if (!$rider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (!$rider->isRider()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not a rider account.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | PAGINATION
            |--------------------------------------------------------------------------
            */

            $perPage = (int) $request->input('per_page', 20);

            $perPage = max(
                1,
                min($perPage, 50)
            );

            /*
            |--------------------------------------------------------------------------
            | RIDER NOTIFICATIONS
            |--------------------------------------------------------------------------
            |
            | Uses Laravel's database notification relationship attached
            | to the authenticated rider User record.
            |
            */

            $notifications = $rider
                ->notifications()
                ->latest()
                ->paginate($perPage);

            /*
            |--------------------------------------------------------------------------
            | FORMAT
            |--------------------------------------------------------------------------
            */

            $data = collect($notifications->items())
                ->map(function ($notification) {
                    $notificationData = is_array($notification->data)
                        ? $notification->data
                        : [];

                    return [
                        'id' => $notification->id,

                        'type' =>
                            $notificationData['type']
                            ?? class_basename($notification->type),

                        'title' =>
                            $notificationData['title']
                            ?? 'BiliHub Notification',

                        'message' =>
                            $notificationData['message']
                            ?? $notificationData['body']
                            ?? '',

                        'shipment_id' =>
                            $notificationData['shipment_id']
                            ?? null,

                        'order_id' =>
                            $notificationData['order_id']
                            ?? null,

                        'seller_order_id' =>
                            $notificationData['seller_order_id']
                            ?? null,

                        'tracking_number' =>
                            $notificationData['tracking_number']
                            ?? null,

                        'url' =>
                            $notificationData['url']
                            ?? null,

                        'data' => $notificationData,

                        'is_read' =>
                            $notification->read_at !== null,

                        'read_at' =>
                            $notification->read_at
                                ?->toISOString(),

                        'created_at' =>
                            $notification->created_at
                                ?->toISOString(),

                        'updated_at' =>
                            $notification->updated_at
                                ?->toISOString(),
                    ];
                })
                ->values();

            /*
            |--------------------------------------------------------------------------
            | UNREAD COUNT
            |--------------------------------------------------------------------------
            */

            $unreadCount = $rider
                ->unreadNotifications()
                ->count();

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success' => true,

                'data' => $data,

                'meta' => [
                    'current_page' =>
                        $notifications->currentPage(),

                    'last_page' =>
                        $notifications->lastPage(),

                    'per_page' =>
                        $notifications->perPage(),

                    'total' =>
                        $notifications->total(),

                    'unread_count' =>
                        $unreadCount,
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to load rider notifications.',
            ], 500);
        }
    }

    /**
     * Mark one rider notification as read.
     *
     * POST /api/rider/notifications/{notification}/read
     */
    public function markAsRead(
        Request $request,
        string $notification
    ): JsonResponse {
        try {
            $rider = $request->user();

            if (!$rider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (!$rider->isRider()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not a rider account.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | IMPORTANT
            |--------------------------------------------------------------------------
            |
            | Search through the authenticated rider's own notifications.
            | This prevents one rider from reading another user's notification.
            |
            */

            $notificationRecord = $rider
                ->notifications()
                ->whereKey($notification)
                ->first();

            if (!$notificationRecord) {
                return response()->json([
                    'success' => false,
                    'message' => 'Notification not found.',
                ], 404);
            }

            if ($notificationRecord->read_at === null) {
                $notificationRecord->markAsRead();
            }

            return response()->json([
                'success' => true,
                'message' =>
                    'Notification marked as read.',

                'data' => [
                    'id' =>
                        $notificationRecord->id,

                    'is_read' => true,

                    'read_at' =>
                        $notificationRecord
                            ->fresh()
                            ->read_at
                            ?->toISOString(),
                ],
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to mark notification as read.',
            ], 500);
        }
    }

    /**
     * Mark all rider notifications as read.
     *
     * POST /api/rider/notifications/read-all
     */
    public function markAllAsRead(
        Request $request
    ): JsonResponse {
        try {
            $rider = $request->user();

            if (!$rider) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated.',
                ], 401);
            }

            if (!$rider->isRider()) {
                return response()->json([
                    'success' => false,
                    'message' => 'This account is not a rider account.',
                ], 403);
            }

            /*
            |--------------------------------------------------------------------------
            | MARK ALL UNREAD NOTIFICATIONS
            |--------------------------------------------------------------------------
            */

            $rider
                ->unreadNotifications
                ->markAsRead();

            return response()->json([
                'success' => true,
                'message' =>
                    'All notifications marked as read.',

                'unread_count' => 0,
            ], 200);
        } catch (\Throwable $e) {
            report($e);

            return response()->json([
                'success' => false,
                'message' =>
                    'Unable to mark notifications as read.',
            ], 500);
        }
    }
}