<?php

namespace Modules\Admin\Services;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\Cache;
use Modules\Admin\Models\Setting;
use Modules\Admin\Repositories\SettingRepository;

class SettingService
{
    protected const CACHE_PREFIX = 'settings:';

    protected const CACHE_TTL = 3600; // 1 hour

    public function __construct(protected SettingRepository $repository) {}

    /**
     * Get a setting value with cascading fallback and caching
     */
    public function get(string $key, ?int $userId = null, ?string $module = null, mixed $default = null): mixed
    {
        $cacheKey = $this->getCacheKey($key, $userId, $module);

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($key, $userId, $module, $default) {
            return $this->repository->get($key, $userId, $module, $default);
        });
    }

    /**
     * Set a setting value and clear cache
     */
    public function set(
        string $key,
        mixed $value,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null,
        ?string $valueType = null,
        ?array $options = null
    ): Setting {
        $setting = $this->repository->set($key, $value, $type, $module, $userId, $valueType, $options);

        // Clear all related caches
        $this->clearCache($key, $userId, $module);

        return $setting;
    }

    /**
     * Get all system settings
     */
    public function getSystemSettings(): Collection
    {
        return Cache::remember(self::CACHE_PREFIX.'system:all', self::CACHE_TTL, function () {
            return $this->repository->getAllSystemSettings();
        });
    }

    /**
     * Get all module settings (optionally filtered by module)
     */
    public function getModuleSettings(?string $module = null): Collection
    {
        $cacheKey = self::CACHE_PREFIX.'module:'.($module ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($module) {
            return $this->repository->getAllModuleSettings($module);
        });
    }

    /**
     * Get all user settings (optionally filtered by user)
     */
    public function getUserSettings(?int $userId = null): Collection
    {
        $cacheKey = self::CACHE_PREFIX.'user:'.($userId ?? 'all');

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($userId) {
            return $this->repository->getAllUserSettings($userId);
        });
    }

    /**
     * Get settings for a specific module
     */
    public function getSettingsByModule(string $module): Collection
    {
        $cacheKey = self::CACHE_PREFIX.'module:'.$module;

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($module) {
            return $this->repository->getSettingsByModule($module);
        });
    }

    /**
     * Get list of all modules that have settings
     */
    public function getModulesList(): SupportCollection
    {
        return Cache::remember(self::CACHE_PREFIX.'modules:list', self::CACHE_TTL, function () {
            return $this->repository->getModulesList();
        });
    }

    /**
     * Get paginated settings
     */
    public function getPaginated(
        int $perPage = 15,
        ?string $type = null,
        ?string $module = null,
        ?int $userId = null,
        ?string $search = null
    ): LengthAwarePaginator {
        return $this->repository->getPaginated($perPage, $type, $module, $userId, $search);
    }

    /**
     * Delete a setting and clear cache
     */
    public function delete(
        string $key,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null
    ): bool {
        $result = $this->repository->delete($key, $type, $module, $userId);

        if ($result) {
            $this->clearCache($key, $userId, $module);
            $this->clearAllCache();
        }

        return $result;
    }

    /**
     * Bulk update settings
     */
    public function bulkUpdate(array $settings): array
    {
        $updated = $this->repository->bulkUpdate($settings);

        // Clear all caches after bulk update
        $this->clearAllCache();

        return $updated;
    }

    /**
     * Get all settings as array
     */
    public function toArray(?string $type = null, ?string $module = null, ?int $userId = null): array
    {
        return $this->repository->toArray($type, $module, $userId);
    }

    /**
     * Check if a setting exists
     */
    public function has(
        string $key,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null
    ): bool {
        return $this->repository->has($key, $type, $module, $userId);
    }

    /**
     * Find a setting by ID
     */
    public function find(int $id): ?Setting
    {
        return $this->repository->find($id);
    }

    /**
     * Clear specific cache key
     */
    protected function clearCache(string $key, ?int $userId = null, ?string $module = null): void
    {
        Cache::forget(self::CACHE_PREFIX.'system:all');
        Cache::forget(self::CACHE_PREFIX.'module:all');
        Cache::forget(self::CACHE_PREFIX.'user:all');
        Cache::forget(self::CACHE_PREFIX.'modules:list');
        if ($module !== null) {
            Cache::forget(self::CACHE_PREFIX.'module:'.$module);
        }
        if ($userId !== null) {
            Cache::forget(self::CACHE_PREFIX.'user:'.$userId);
        }

        // Clear the specific setting cache
        Cache::forget($this->getCacheKey($key, $userId, $module));

        // Clear user cache if userId is provided
        if ($userId) {
            Cache::forget($this->getCacheKey($key, null, $module));
        }

        // Clear module cache if module is provided
        if ($module) {
            Cache::forget($this->getCacheKey($key, $userId, null));
        }

        // Clear system cache
        Cache::forget($this->getCacheKey($key, null, null));
    }

    /**
     * Clear all settings cache
     */
    public function clearAllCache(): void
    {
        Cache::flush();
    }

    /**
     * Generate cache key for a setting
     */
    protected function getCacheKey(string $key, ?int $userId = null, ?string $module = null): string
    {
        $parts = [self::CACHE_PREFIX, $key];

        if ($userId) {
            $parts[] = 'user:'.$userId;
        }

        if ($module) {
            $parts[] = 'module:'.$module;
        }

        return implode(':', $parts);
    }
}
