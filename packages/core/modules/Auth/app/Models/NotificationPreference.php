<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Auth notification preferences for users.
 *
 * Controls which channels (mail, database, SMS, push) users receive
 * notifications through for different authentication events.
 */
class NotificationPreference extends Model
{
    use HasFactory;

    protected $table = 'auth_notification_preferences';

    protected $fillable = [
        'user_id',
        'category',
        'channels',
        'is_enabled',
        'cooldown_minutes',
    ];

    protected $casts = [
        'channels' => 'array',
        'is_enabled' => 'boolean',
        'cooldown_minutes' => 'integer',
    ];

    protected static function booted(): void
    {
        // Set default channels if not provided
        static::creating(function (self $model) {
            if (empty($model->channels)) {
                $model->channels = ['mail'];
            }
        });
    }

    /**
     * Relationship: belongs to user.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Check if a specific channel is enabled for this preference.
     */
    public function isChannelEnabled(string $channel): bool
    {
        return $this->is_enabled && in_array($channel, $this->channels ?? []);
    }

    /**
     * Get preference for user and category or create default.
     */
    public static function getOrCreate(int $userId, string $category, array $defaults = []): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'category' => $category],
            array_merge([
                'channels' => ['mail'],
                'is_enabled' => true,
                'cooldown_minutes' => 0,
            ], $defaults)
        );
    }
}
