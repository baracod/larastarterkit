<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Permission extends Model
{
    use HasFactory;

    protected $table = 'auth_permissions';

    protected $fillable = [
        'key',
        'action',
        'subject',
        'description',
        'table_name',
        'always_allow',
        'is_public',
    ];

    public function roles()
    {
        return $this->belongsToMany(Role::class, 'auth_role_permissions');
    }

    public function role_permission()
    {
        return $this->belongsTo(RolePermission::class);
    }
}
