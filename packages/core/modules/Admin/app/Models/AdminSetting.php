<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminSetting extends Model
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

    protected $casts = [
        'user_id' => 'integer',
        'is_public' => 'boolean',
    ];
}
