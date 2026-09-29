<?php

namespace Modules\Auth\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Modules\Auth\Http\Requests\UserNotificationIndexRequest;
use Modules\Auth\Services\UserNotificationService;

class UserNotificationController extends Controller
{
    public function __construct(protected UserNotificationService $notificationService) {}

    public function index(UserNotificationIndexRequest $request, int $id): JsonResponse
    {
        $authUser = $request->user();

        if (! $authUser) {
            return ApiResponse::unauthorized();
        }

        $result = $this->notificationService->listForUser(
            $authUser,
            $id,
            $request->validated()
        );

        return ApiResponse::success($result);
    }

    public function markAsRead(int $id, int $notificationId): JsonResponse
    {
        $authUser = request()->user();

        if (! $authUser) {
            return ApiResponse::unauthorized();
        }

        $notification = $this->notificationService->markAsRead(
            $authUser,
            $id,
            $notificationId
        );

        return ApiResponse::success($notification, 'Notification marquee comme lue.');
    }

    public function markAllAsRead(int $id): JsonResponse
    {
        $authUser = request()->user();

        if (! $authUser) {
            return ApiResponse::unauthorized();
        }

        $updated = $this->notificationService->markAllAsRead($authUser, $id);

        return ApiResponse::success(['updated' => $updated], 'Toutes les notifications ont ete marquees comme lues.');
    }
}
