<?php

namespace Modules\Auth\Http\Controllers;

use Baracod\Larastarterkit\Core\Helpers\ApiResponse;
use Baracod\Larastarterkit\Core\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Auth\Models\NotificationPreference;

/**
 * Manage user notification preferences for authentication events.
 *
 * Users can customize which channels they receive notifications through
 * for different authentication-related events.
 */
class NotificationPreferenceController extends Controller
{
    /**
     * Get all notification preferences for authenticated user.
     */
    public function index(): JsonResponse
    {
        $preferences = auth()->user()
            ->notificationPreferences()
            ->select('id', 'category', 'channels', 'is_enabled', 'cooldown_minutes')
            ->get();

        return ApiResponse::success($preferences);
    }

    /**
     * Get specific notification preference by category.
     */
    public function show(string $category): JsonResponse
    {
        $preference = NotificationPreference::getOrCreate(
            auth()->id(),
            $category,
            ['channels' => ['mail']]
        );

        return ApiResponse::success($preference);
    }

    /**
     * Update notification preference for a category.
     */
    public function update(Request $request, string $category): JsonResponse
    {
        $validated = $request->validate([
            'channels' => 'array|required|min:1',
            'channels.*' => 'string|in:mail,database,sms,push',
            'is_enabled' => 'boolean',
            'cooldown_minutes' => 'integer|min:0|max:1440',
        ]);

        $preference = NotificationPreference::getOrCreate(
            auth()->id(),
            $category
        );

        $preference->update($validated);

        return ApiResponse::success($preference, "Préférences mises à jour pour '{$category}'.");
    }

    /**
     * Reset preference to defaults for a category.
     */
    public function reset(string $category): JsonResponse
    {
        $preference = auth()->user()
            ->notificationPreferences()
            ->where('category', $category)
            ->firstOrFail();

        $preference->update([
            'channels' => ['mail'],
            'is_enabled' => true,
            'cooldown_minutes' => 0,
        ]);

        return ApiResponse::success($preference, "Préférences réinitialisées pour '{$category}'.");
    }

    /**
     * Disable all notifications for a category.
     */
    public function disable(string $category): JsonResponse
    {
        $preference = NotificationPreference::getOrCreate(
            auth()->id(),
            $category
        );

        $preference->update(['is_enabled' => false]);

        return ApiResponse::success($preference, "Notifications désactivées pour '{$category}'.");
    }

    /**
     * Enable all notifications for a category.
     */
    public function enable(string $category): JsonResponse
    {
        $preference = NotificationPreference::getOrCreate(
            auth()->id(),
            $category
        );

        $preference->update(['is_enabled' => true]);

        return ApiResponse::success($preference, "Notifications activées pour '{$category}'.");
    }
}
