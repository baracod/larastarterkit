<?php

namespace Modules\Auth\Models;

use Baracod\Larastarterkit\Core\Larastarterkit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserModuleSetting extends Model
{
    protected $table = 'auth_user_module_settings';

    protected $fillable = [
        'user_id',
        'module',
        'key',
        'value',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Larastarterkit::userModel(), 'user_id', 'id');
    }
}
