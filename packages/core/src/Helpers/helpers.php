<?php

use Baracod\Larastarterkit\Core\Helpers\SettingHelper;

if (! function_exists('setting')) {
    /**
     * Get or set a setting value with cascading fallback (user -> module -> system)
     *
     * Usage:
     * - setting('app.name') // Get system setting
     * - setting('theme', auth()->id()) // Get user setting with fallback
     * - setting('notification.enabled', null, 'admin') // Get module setting
     * - setting('app.name', null, null, 'Default Name') // Get with default value
     *
     * @param  string  $key  Setting key
     * @param  int|null  $userId  Optional user ID for user-specific settings
     * @param  string|null  $module  Optional module name for module-specific settings
     * @param  mixed  $default  Default value if setting not found
     * @return mixed
     */
    function setting(string $key, ?int $userId = null, ?string $module = null, $default = null)
    {
        return SettingHelper::get($key, $userId, $module, $default);
    }
}
