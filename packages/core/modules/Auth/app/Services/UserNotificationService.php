<?php

namespace Modules\Auth\Services;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Admin\Models\Notification;
use Modules\Auth\Models\User;

class UserNotificationService
{
    public function listForUser(User $authUser, int $targetUserId, array $filters = []): array
    {
        $this->ensureCanAccess($authUser, $targetUserId);

        $status = $filters['status'] ?? 'all';
        $sort = $filters['sort'] ?? 'newest';
        $perPage = (int) ($filters['per_page'] ?? 10);

        $query = Notification::query()->forUser($targetUserId);

        if ($status === 'read') {
            $query->whereNotNull('read_at');
        }

        if ($status === 'unread') {
            $query->whereNull('read_at');
        }

        if ($sort === 'oldest') {
            $query->orderBy('created_at');
        } else {
            $query->orderByDesc('created_at');
        }

        $paginator = $query->paginate($perPage);

        return [
            'notifications' => $paginator->items(),
            'pagination' => $this->paginationMeta($paginator),
            'unread_count' => Notification::query()->forUser($targetUserId)->unread()->count(),
        ];
    }

    public function markAsRead(User $authUser, int $targetUserId, int $notificationId): Notification
    {
        $this->ensureCanAccess($authUser, $targetUserId);

        $notification = Notification::query()
            ->forUser($targetUserId)
            ->findOrFail($notificationId);

        if ($notification->read_at === null) {
            $notification->update(['read_at' => now()]);
        }

        return $notification->fresh();
    }

    public function markAllAsRead(User $authUser, int $targetUserId): int
    {
        $this->ensureCanAccess($authUser, $targetUserId);

        return Notification::query()
            ->forUser($targetUserId)
            ->unread()
            ->update(['read_at' => now()]);
    }

    protected function ensureCanAccess(User $authUser, int $targetUserId): void
    {
        if ($authUser->id !== $targetUserId && ! $authUser->hasRole('administrator')) {
            abort(403, 'Vous n\'etes pas autorise a acceder a ces notifications.');
        }
    }

    protected function paginationMeta(LengthAwarePaginator $paginator): array
    {
        return [
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'per_page' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }
}
