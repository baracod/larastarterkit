<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Auth\Models\User;

class Setting extends Model
{
    use HasFactory;

    protected $table = 'admin_settings';

    protected $fillable = [
        'type',
        'module',
        'user_id',
        'key',
        'value',
        'value_type',
        'label',
        'description',
        'input_type',
        'options',
        'default_value',
        'is_public',
    ];

    protected function casts(): array
    {
        return [
            'options' => AsCollection::class,
            'is_public' => 'boolean',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * Get the user associated with this user setting
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Scope: Get system settings
     */
    public function scopeSystem(Builder $query): Builder
    {
        return $query->where('type', 'system');
    }

    /**
     * Scope: Get module settings
     */
    public function scopeModuleSettings(Builder $query, ?string $module = null): Builder
    {
        $query = $query->where('type', 'module');
        if ($module) {
            $query = $query->where('module', $module);
        }

        return $query;
    }

    /**
     * Scope: Get user settings
     */
    public function scopeUserSettings(Builder $query, ?int $userId = null): Builder
    {
        $query = $query->where('type', 'user');
        if ($userId) {
            $query = $query->where('user_id', $userId);
        }

        return $query;
    }

    /**
     * Scope: Get settings for a specific module
     */
    public function scopeForModule(Builder $query, string $module): Builder
    {
        return $query->where('module', $module);
    }

    /**
     * Scope: Get settings for a specific user
     */
    public function scopeForUser(Builder $query, int $userId): Builder
    {
        return $query->where('user_id', $userId);
    }

    /**
     * Get a setting by key, with cascading fallback (user -> module -> system)
     */
    public static function getValue(string $key, ?int $userId = null, ?string $module = null, mixed $default = null): mixed
    {
        // Try user setting first
        if ($userId) {
            $setting = static::where('type', 'user')
                ->where('key', $key)
                ->where('user_id', $userId)
                ->first();

            if ($setting) {
                return static::castValue($setting->value, $setting->value_type);
            }
        }

        // Then try module setting
        if ($module) {
            $setting = static::where('type', 'module')
                ->where('key', $key)
                ->where('module', $module)
                ->first();

            if ($setting) {
                return static::castValue($setting->value, $setting->value_type);
            }
        }

        // Finally try system setting
        $setting = static::where('type', 'system')
            ->where('key', $key)
            ->whereNull('module')
            ->whereNull('user_id')
            ->first();

        if ($setting) {
            return static::castValue($setting->value, $setting->value_type);
        }

        return $default;
    }

    /**
     * Cast the value based on value_type
     */
    public static function castValue(mixed $value, ?string $type): mixed
    {
        $normalizedType = $type ?: 'string';

        return match ($normalizedType) {
            'boolean' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer' => (int) $value,
            'float' => (float) $value,
            'array', 'json' => json_decode($value, true),
            default => $value,
        };
    }

    /**
     * Create or update a setting
     */
    public static function setSetting(
        string $key,
        mixed $value,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null,
        ?string $valueType = null,
        ?array $options = null
    ): static {
        $valueType = $valueType ?? static::inferType($value);

        return static::updateOrCreate(
            [
                'type' => $type,
                'key' => $key,
                'module' => $module,
                'user_id' => $userId,
            ],
            [
                'value' => static::encodeValue($value, $valueType),
                'value_type' => $valueType,
                'options' => $options,
            ]
        );
    }

    /**
     * Infer the value type based on the value
     */
    public static function inferType(mixed $value): string
    {
        if (is_bool($value)) {
            return 'boolean';
        }
        if (is_int($value)) {
            return 'integer';
        }
        if (is_float($value)) {
            return 'float';
        }
        if (is_array($value) || is_object($value)) {
            return 'array';
        }

        return 'string';
    }

    /**
     * Encode the value for storage
     */
    public static function encodeValue(mixed $value, string $type): string
    {
        return match ($type) {
            'boolean' => $value ? '1' : '0',
            'array', 'json' => json_encode($value),
            default => (string) $value,
        };
    }

    /**
     * Delete a setting
     */
    public static function deleteSetting(
        string $key,
        string $type = 'system',
        ?string $module = null,
        ?int $userId = null
    ): bool {
        return (bool) static::where('type', $type)
            ->where('key', $key)
            ->where('module', $module)
            ->where('user_id', $userId)
            ->delete();
    }
}
