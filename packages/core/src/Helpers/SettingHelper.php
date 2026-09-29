<?php

namespace Baracod\Larastarterkit\Core\Helpers;

use Modules\Admin\Services\SettingService;

/**
 * Helper class for accessing settings
 */
class SettingHelper
{
    protected static ?SettingService $service = null;

    /**
     * Get the setting service instance
     */
    protected static function getService(): SettingService
    {
        if (! self::$service) {
            self::$service = app(SettingService::class);
        }

        return self::$service;
    }

    /**
     * Get a setting value with cascading fallback (user -> module -> system)
     *
     * @param  string  $key  Setting key (e.g., 'app.name', 'notification.enabled')
     * @param  int|null  $userId  Optional user ID for user-specific settings
     * @param  string|null  $module  Optional module name for module-specific settings
     * @param  mixed  $default  Default value if setting not found
     * @return mixed
     */
    public static function get(string $key, ?int $userId = null, ?string $module = null, $default = null)
    {
        return self::getService()->get($key, $userId, $module, $default);
    }

    /**
     * Set a setting value
     *
     * @param  string  $key  Setting key
     * @param  mixed  $value  Setting value
     * @param  string  $type  Setting type: 'system', 'module', or 'user'
     * @param  string|null  $module  Module name (required for module settings)
     * @param  int|null  $userId  User ID (required for user settings)
     * @param  string|null  $valueType  Value type (auto-detected if null)
     * @param  array|null  $options  Additional options
     */
    public static function set(
        string $key,
        $value,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null,
        ?string $valueType = null,
        ?array $options = null
    ) {
        return self::getService()->set($key, $value, $type, $module, $userId, $valueType, $options);
    }

    /**
     * Check if a setting exists
     */
    public static function has(
        string $key,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null
    ): bool {
        return self::getService()->has($key, $type, $module, $userId);
    }

    /**
     * Delete a setting
     */
    public static function delete(
        string $key,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null
    ): bool {
        return self::getService()->delete($key, $type, $module, $userId);
    }
}
