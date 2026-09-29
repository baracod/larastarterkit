<?php

namespace Modules\Admin\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Admin\Models\Setting;

class SettingRepository
{
    /**
     * Get all system settings
     */
    public function getAllSystemSettings(): Collection
    {
        return Setting::system()->get();
    }

    /**
     * Get all module settings
     */
    public function getAllModuleSettings(?string $module = null): Collection
    {
        return Setting::moduleSettings($module)->get();
    }

    /**
     * Get all user settings
     */
    public function getAllUserSettings(?int $userId = null): Collection
    {
        return Setting::userSettings($userId)->get();
    }

    /**
     * Get settings grouped by module
     */
    public function getSettingsByModule(string $module): Collection
    {
        return Setting::moduleSettings($module)->get();
    }

    /**
     * Get all modules that have settings
     */
    public function getModulesList(): SupportCollection
    {
        return Setting::moduleSettings()
            ->select('module')
            ->distinct()
            ->pluck('module')
            ->sort()
            ->values();
    }

    /**
     * Get a single setting by key with cascading fallback
     */
    public function get(string $key, ?int $userId = null, ?string $module = null, mixed $default = null): mixed
    {
        return Setting::getValue($key, $userId, $module, $default);
    }

    /**
     * Create or update a setting
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
        return Setting::setSetting($key, $value, $type, $module, $userId, $valueType, $options);
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
        $query = Setting::query();

        if ($type) {
            $query = $query->where('type', $type);
        }

        if ($module) {
            $query = $query->where('module', $module);
        }

        if ($userId) {
            $query = $query->where('user_id', $userId);
        }

        if ($search) {
            $query = $query->where(function ($q) use ($search) {
                $q->where('key', 'like', "%{$search}%")
                    ->orWhere('label', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        return $query->paginate($perPage);
    }

    /**
     * Delete a setting
     */
    public function delete(
        string $key,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null
    ): bool {
        return Setting::deleteSetting($key, $type, $module, $userId);
    }

    /**
     * Get all settings as array for easier access
     */
    public function toArray(?string $type = null, ?string $module = null, ?int $userId = null): array
    {
        $query = Setting::query();

        if ($type) {
            $query = $query->where('type', $type);
        }

        if ($module) {
            $query = $query->where('module', $module);
        }

        if ($userId) {
            $query = $query->where('user_id', $userId);
        }

        return $query->get()->mapWithKeys(function ($setting) {
            return [$setting->key => Setting::castValue($setting->value, $setting->value_type)];
        })->toArray();
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
        return Setting::where('type', $type)
            ->where('key', $key)
            ->where('module', $module)
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * Find a setting by ID
     */
    public function find(int $id): ?Setting
    {
        return Setting::find($id);
    }

    /**
     * Bulk update settings
     */
    public function bulkUpdate(array $settings): array
    {
        $updated = [];

        foreach ($settings as $setting) {
            $updated[] = $this->set(
                key: $setting['key'] ?? null,
                value: $setting['value'] ?? null,
                type: $setting['type'] ?? 'system',
                module: $setting['module'] ?? null,
                userId: $setting['user_id'] ?? null,
                valueType: $setting['value_type'] ?? null,
                options: $setting['options'] ?? null
            );
        }

        return $updated;
    }
}
