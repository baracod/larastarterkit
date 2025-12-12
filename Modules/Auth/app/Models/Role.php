<?php

namespace Modules\Auth\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    use HasFactory;

    protected $table = 'auth_roles';

    protected $fillable = [
        'name',
        'display_name',
        'description',
        'order',
        'is_owner'
    ];

    // protected $appends = ['nbr_user'];

    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'auth_role_permissions');
    }

    public function users()
    {
        return $this->belongsToMany(User::class, 'auth_user_roles', 'role_id', 'user_id');
    }

    // public function getNbrUserAttribute()
    // {
    //     return $this->users()->count() ??  $this->users()->count();
    // }
}
