<?php

namespace Modules\Auth\Models;

use Modules\Auth\Models\Role;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Notifications\Notifiable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Modules\Auth\Notifications\ResetPasswordLink;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;




class User extends  Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;
    protected $table = 'auth_users';

    protected $fillable = [
        'name',
        'username',
        'email',
        'additional_info',
        'avatar',
        'password',
        'active'
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    protected $appends = [
        'role_names',
        'avatar_url'
    ];


    protected static function booted()
    {
        //Avant la suppression, supprimer les données pivos
        static::deleting(function ($user) {
            $user->roles()->detach(); // Remove all roles when user is deleted
        });

        static::creating(function ($user) {
            // Par exemple, forcer le nom d'utilisateur en minuscules
            $user->username = strtolower($user->username);
        });
    }

    /**
     * Relation entre l'utilisateur et ses rôles.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'auth_user_roles');
    }

    public function getRoleNamesAttribute()
    {
        return $this->roles->pluck('display_name');
    }


    public function hasRole($roleName)
    {
        return $this->roles()->where('name', $roleName)->exists();
    }
    /**
     * Relation entre l'utilisateur et ses permissions via ses rôles.
     */
    // public function can($permission,  $arguments = [])
    // {
    //     return $this->permissions()->where('auth_permissions.key', $permission)->exists();
    // }

    public function can($action, $subject = null)
    {
        return $this->permissions()
            ->where('auth_permissions.action', $action)
            ->where('auth_permissions.subject', $subject)
            ->exists();
    }

    public function permissions()
    {
        return Permission::query()
            ->select('auth_permissions.*')
            ->join('auth_role_permissions as arp', 'arp.permission_id', '=', 'auth_permissions.id')
            ->join('auth_user_roles as aur', 'aur.role_id', '=', 'arp.role_id')
            ->where('aur.user_id', $this->getKey())
            ->distinct();
    }

    //-------------------------GETTERS-------------------------------------

    /**
     * Get the full URL for the user's avatar.
     *
     * @param  string|null  $value
     * @return string|null
     */
    public function getAvatarAttribute($value)
    {
        if ($value) {
            return Storage::url($value);
        }

        return null;
    }

    // L’attribut URL dérivé
    protected function avatarUrl(): Attribute
    {
        return Attribute::get(function () {
            return $this->attributes['avatar']
                ? Storage::url($this->attributes['avatar'])
                : null; // ou une URL par défaut si tu veux un placeholder
        });
    }

    /**
     * Récupère uniquement les utilisateurs qui ne sont pas administrateurs.
     */
    public static function nonAdmin()
    {
        return self::with('auth_roles')->get()->reject(fn($user) => $user->hasRole('administrator'))->values();
    }

    /**
     * Récupère les utilisateurs avec des rôles d'ordre inférieur à une valeur donnée.
     */
    public static function withLowerRoles($order)
    {
        return self::with('auth_roles')->get()->filter(function ($user) use ($order) {
            return !$user->roles->where('order', '<=', $order)->count()
                && $user->roles->where('order', '>', $order)->count();
        })->values();
    }

    public function preferredLocale(?string $lang = null): ?string
    {
        return $lang ?: 'fr';
    }


    //-------------------------NOTIFICATIONS-------------------------------------
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordLink($token));
    }
}
