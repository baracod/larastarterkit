<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class RolePermission extends Model
{
    use LogsActivity;

    protected $table = null;

    /**
     * Constructor for setting the table name dynamically.
     */
    public function __construct(array $attributes = [])
    {
        $this->table = 'auth_role_permissions';
        parent::__construct($attributes);
    }

    protected $fillable = [
        'id',
        'role_id',
        'permission_id',
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'auth_roles');
    }

    public function permission()
    {
        return $this->belongsTo(Permission::class);
    }

    protected static $logAttributes = true;
    protected static $logFillable = true;
    protected static $logName = 'RolePermission';

    public function getDescriptionForEvent(string $eventName): string
    {
        return "This model has been {$eventName}";
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->dontSubmitEmptyLogs();
    }
}
