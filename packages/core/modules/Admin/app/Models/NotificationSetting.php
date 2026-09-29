<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// use Modules\Admin\Database\Factories\NotificationSettingFactory;

class NotificationSetting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     */
    protected $table = 'admin_notification_settings';

    protected $fillable = [
        'notification_type',
        'channels',
        'is_enabled',
    ];

    protected $casts = [
        'channels' => 'array',
        'is_enabled' => 'boolean',
    ];

    // protected static function newFactory(): NotificationSettingFactory
    // {
    //     // return NotificationSettingFactory::new();
    // }
}
