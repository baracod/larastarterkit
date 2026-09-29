<?php

namespace Modules\Admin\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ModuleConfig extends Model
{
    use HasFactory;

    protected $table = 'admin_module_configs';

    protected $fillable = [
        'module_name',
        'key',
        'value',
        'type',
        'description',
    ];

    protected $casts = [
        'value' => 'array',
    ];

    public static function getValue(string $moduleName, string $key, $default = null)
    {
        $config = static::where('module_name', $moduleName)->where('key', $key)->first();

        return $config ? $config->value : $default;
    }

    public static function setValue(string $moduleName, string $key, $value, ?string $type = 'string', ?string $description = null)
    {
        return static::updateOrCreate(
            ['module_name' => $moduleName, 'key' => $key],
            ['value' => $value, 'type' => $type ?? 'string', 'description' => $description]
        );
    }
}
