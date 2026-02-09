<?php

namespace Modules\Auth\Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Créer les rôles de base
        $roles = [
            [
                'name' => 'super-admin',
                'display_name' => 'Super Administrateur',
                'description' => 'Accès complet au système',
                'order' => 1,
                'is_owner' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'admin',
                'display_name' => 'Administrateur',
                'description' => 'Administrateur du système',
                'order' => 2,
                'is_owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'name' => 'user',
                'display_name' => 'Utilisateur',
                'description' => 'Utilisateur standard',
                'order' => 3,
                'is_owner' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($roles as $role) {
            DB::table('auth_roles')->updateOrInsert(
                ['name' => $role['name']],
                $role
            );
        }

        // Créer les permissions de base
        $permissions = [
            // Permissions publiques
            [
                'key' => 'view-public-content',
                'action' => 'view',
                'subject' => 'public-content',
                'description' => 'Voir le contenu public',
                'table_name' => null,
                'always_allow' => false,
                'is_public' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Permissions utilisateur
            [
                'key' => 'view-dashboard',
                'action' => 'view',
                'subject' => 'dashboard',
                'description' => 'Accéder au tableau de bord',
                'table_name' => null,
                'always_allow' => false,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'edit-own-profile',
                'action' => 'edit',
                'subject' => 'own-profile',
                'description' => 'Modifier son propre profil',
                'table_name' => 'auth_users',
                'always_allow' => true,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            // Permissions admin
            [
                'key' => 'manage-users',
                'action' => 'manage',
                'subject' => 'users',
                'description' => 'Gérer les utilisateurs',
                'table_name' => 'auth_users',
                'always_allow' => false,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'manage-roles',
                'action' => 'manage',
                'subject' => 'roles',
                'description' => 'Gérer les rôles',
                'table_name' => 'auth_roles',
                'always_allow' => false,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'key' => 'manage-permissions',
                'action' => 'manage',
                'subject' => 'permissions',
                'description' => 'Gérer les permissions',
                'table_name' => 'auth_permissions',
                'always_allow' => false,
                'is_public' => false,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ];

        foreach ($permissions as $permission) {
            DB::table('auth_permissions')->updateOrInsert(
                ['key' => $permission['key']],
                $permission
            );
        }

        // Assigner les permissions aux rôles
        $superAdminRole = DB::table('auth_roles')->where('name', 'super-admin')->first();
        $adminRole = DB::table('auth_roles')->where('name', 'admin')->first();
        $userRole = DB::table('auth_roles')->where('name', 'user')->first();

        if ($superAdminRole) {
            // Super Admin a toutes les permissions
            $allPermissions = DB::table('auth_permissions')->get();
            foreach ($allPermissions as $permission) {
                DB::table('auth_role_permissions')->updateOrInsert(
                    [
                        'role_id' => $superAdminRole->id,
                        'permission_id' => $permission->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if ($adminRole) {
            // Admin a les permissions de gestion
            $adminPermissions = DB::table('auth_permissions')
                ->whereIn('key', ['view-dashboard', 'edit-own-profile', 'manage-users', 'manage-roles'])
                ->get();
            foreach ($adminPermissions as $permission) {
                DB::table('auth_role_permissions')->updateOrInsert(
                    [
                        'role_id' => $adminRole->id,
                        'permission_id' => $permission->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        if ($userRole) {
            // User a les permissions de base
            $userPermissions = DB::table('auth_permissions')
                ->whereIn('key', ['view-public-content', 'view-dashboard', 'edit-own-profile'])
                ->get();
            foreach ($userPermissions as $permission) {
                DB::table('auth_role_permissions')->updateOrInsert(
                    [
                        'role_id' => $userRole->id,
                        'permission_id' => $permission->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        // Créer un utilisateur super-admin par défaut
        $superAdminUser = DB::table('auth_users')->updateOrInsert(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Super Admin',
                'username' => 'superadmin',
                'password' => Hash::make('password'),
                'email_verified_at' => now(),
                'active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]
        );

        // Assigner le rôle super-admin au premier utilisateur
        if ($superAdminRole) {
            $user = DB::table('auth_users')->where('email', 'admin@example.com')->first();
            if ($user) {
                DB::table('auth_user_roles')->updateOrInsert(
                    [
                        'user_id' => $user->id,
                        'role_id' => $superAdminRole->id,
                    ],
                    [
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }
    }
}
