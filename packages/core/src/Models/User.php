<?php

namespace Baracod\Larastarterkit\Core\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Activitylog\LogOptions;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Les attributs mass assignables.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    protected $table = 'auth_users';

    /**
     * Les attributs cachés pour la sérialisation.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Les attributs par défaut d'une nouvelle instance (utilisés notamment par
     * les tests via Sanctum::actingAs sur un modèle non persisté).
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'active' => true,
    ];

    /**
     * Casting des attributs.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'active' => 'boolean',
        ];
    }

    /**
     * Relation entre l'utilisateur et ses rôles.
     */
    public function roles()
    {
        return $this->belongsToMany(Role::class, 'auth_user_roles');
    }

    /**
     * Relation entre l'utilisateur et ses permissions via ses rôles.
     */
    public function permissions()
    {
        return $this->belongsToMany(Permission::class, 'auth_role_permissions', 'role_id', 'permission_id');

        // dd($permissions->get());
        // $this->roles()->each(function ($role) {
        //     $role->permissions->each(function ($permission) {
        //         $this->permissions->add($permission);
        //     });
        // });
    }

    /**
     * Vérifie si l'utilisateur a un rôle donné.
     */
    public function hasRole(string $role): bool
    {

        return $this->roles()->where('name', $role)->exists();
    }

    /**
     * Vérifie si l'utilisateur a une permission donnée.
     */
    public function can($ability, $arguments = []): bool
    {
        return $this->permissions()->pluck('key')->contains($ability) || parent::can($ability, $arguments);
    }

    /**
     * Récupère uniquement les utilisateurs qui ne sont pas administrateurs.
     */
    public static function nonAdmin()
    {
        return self::with('auth_roles')->get()->reject(fn ($user) => $user->hasRole('administrator'))->values();
    }

    /**
     * Récupère les utilisateurs avec des rôles d'ordre inférieur à une valeur donnée.
     */
    public static function withLowerRoles($order)
    {
        return self::with('auth_roles')->get()->filter(function ($user) use ($order) {
            return ! $user->roles->where('order', '<=', $order)->count()
                && $user->roles->where('order', '>', $order)->count();
        })->values();
    }

    public function getAvatarAttribute($value)
    {
        if ($value) {
            return asset('storage/'.$value);
        }

        // Fallback to UI Avatars if no avatar is stored
        return 'https://ui-avatars.com/api/?name='.urlencode($this->name).'&background=random';
    }

    /**
     * Configuration des logs d'activité.
     */
    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->dontSubmitEmptyLogs();
    }
}
