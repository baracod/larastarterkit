<?php

namespace Modules\Admin\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Admin\Events\NotificationCreated;
use Modules\Admin\Models\Notification;

class NotificationController extends Controller
{
    protected $notificationService;

    public function __construct(\Modules\Admin\Services\NotificationService $notificationService)
    {
        $this->notificationService = $notificationService;
    }

    public function index(Request $request): JsonResponse
    {
        $notifications = Notification::forUser(auth()->id())
            ->orderByDesc('created_at')
            ->paginate(20);

        return response()->json($notifications);
    }

    public function unreadCount(): JsonResponse
    {
        $count = Notification::forUser(auth()->id())->unread()->count();

        return response()->json(['count' => $count]);
    }

    public function markAsRead(int $id): JsonResponse
    {
        $notification = Notification::forUser(auth()->id())->findOrFail($id);
        $notification->update(['read_at' => now()]);

        return ApiResponse::success($notification, 'Notification marquée comme lue.');
    }

    public function markAllAsRead(): JsonResponse
    {
        Notification::forUser(auth()->id())->unread()->update(['read_at' => now()]);

        return ApiResponse::success(null, 'Toutes les notifications marquées comme lues.');
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'user_id' => 'required|exists:auth_users,id',
            'type' => 'required|string',
            'title' => 'required|string',
            'message' => 'nullable|string',
            'data' => 'nullable|array',
        ]);

        $notification = Notification::create($data);

        // Broadcast l'événement pour le temps réel
        broadcast(new NotificationCreated($notification))->toOthers();

        return ApiResponse::success($notification, 'Notification créée.');
    }

    public function getSettings(): JsonResponse
    {
        $settings = $this->notificationService->getAllSettings();

        return ApiResponse::success($settings);
    }

    public function updateSetting(Request $request): JsonResponse
    {
        $request->validate([
            'type' => 'required|string',
            'channels' => 'required|array',
            'enabled' => 'required|boolean',
        ]);

        $setting = $this->notificationService->updateSetting(
            $request->type,
            $request->channels,
            $request->enabled
        );

        return ApiResponse::success($setting, 'Configuration mise à jour.');
    }
}
