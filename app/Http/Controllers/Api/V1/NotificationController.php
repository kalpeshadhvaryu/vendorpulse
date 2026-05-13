<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Resources\Api\V1\DatabaseNotificationResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class NotificationController extends BaseApiController
{
    public function index(Request $request): JsonResponse
    {
        $perPage = min((int) $request->query('per_page', 25), 100);

        $paginator = $request->user()
            ->notifications()
            ->orderByDesc('created_at')
            ->paginate($perPage);

        return ApiResponse::fromResource(
            DatabaseNotificationResource::collection($paginator)
        );
    }

    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->whereKey($id)->first();

        if (! $notification) {
            return ApiResponse::error('Notification not found.', Response::HTTP_NOT_FOUND);
        }

        $notification->markAsRead();

        return ApiResponse::success(
            new DatabaseNotificationResource($notification->fresh()),
            'Notification marked as read.'
        );
    }
}
