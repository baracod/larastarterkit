<?php

declare(strict_types=1);

namespace Modules\Auth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Auth\Models\Role;
use Modules\Auth\Models\User;
use Throwable;

/**
 * Crée (ou met à jour) le super administrateur et initialise ses accès.
 *
 * Idempotente : peut être relancée sans risque, elle ne duplique ni
 * l'utilisateur, ni le rôle, ni les permissions attachées.
 */
final class CreateSuperAdminCommand extends Command
{
    private const ROLE_NAME = 'administrator';

    protected $aliases = ['starter:super-admin'];

    protected $signature = 'auth:super-admin:create
        {--name= : Nom complet du super administrateur}
        {--username= : Pseudonyme unique (généré depuis l\'email si absent)}
        {--email= : Email unique du super administrateur}
        {--password= : Mot de passe (demande interactive masquée sinon)}
        {--reset-access : Supprime puis rattache TOUTES les permissions au rôle administrator}';

    protected $description = 'Crée le super administrateur ou initialise ses accès';

    public function handle(): int
    {
        $email = $this->resolveEmail();

        if ($email === '') {
            return self::FAILURE;
        }

        $existing = User::where('email', $email)->first();
        $name = $this->resolveName($existing !== null);
        $password = $this->resolvePassword($existing !== null);

        if ($name === '' && ! $existing) {
            $this->error('Le nom est requis pour créer un nouveau super administrateur (option --name).');

            return self::FAILURE;
        }

        $username = $this->resolveUsername($email);

        if ($password === null && ! $existing) {
            $this->error('Le mot de passe est requis pour créer un nouveau super administrateur.');

            return self::FAILURE;
        }
        if ($password !== null && mb_strlen($password) < 8) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères.');

            return self::FAILURE;
        }

        [$role, $user, $attached] = DB::transaction(function () use ($name, $username, $email, $password, $existing): array {
            $role = $this->ensureRole();
            $user = $this->ensureUser($name, $username, $email, $password, $existing);
            $this->ensureUserRole($user, $role);

            return [$role, $user, $this->ensureRolePermissions($role)];
        });

        $this->info('Super administrateur prêt.');
        $this->table(
            ['Champ', 'Valeur'],
            [
                ['Nom', $user->name],
                ['Email', $user->email],
                ['Pseudonyme', (string) $user->username],
                ['Rôle', $role->name],
                ['Permissions liées au rôle', (string) $attached],
            ]
        );

        return self::SUCCESS;
    }

    /**
     * @return array{0: string, 1: string}|null [question, défaut] ou null sans prompt
     */
    private function promptFallback(string $question, string $default = ''): string
    {
        try {
            return trim((string) $this->ask($question, $default));
        } catch (Throwable) {
            return '';
        }
    }

    private function resolveName(bool $userExists): string
    {
        $name = $this->option('name');

        if (is_string($name) && $name !== '') {
            return $name;
        }

        if ($userExists) {
            return '';
        }

        return $this->promptFallback('Nom complet du super administrateur', 'Super Administrateur');
    }

    private function resolveEmail(): string
    {
        $email = is_string($this->option('email')) ? trim($this->option('email')) : '';

        if ($email === '') {
            $email = $this->promptFallback('Email unique du super administrateur');
        }

        if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Email invalide : '.$email);

            return '';
        }

        return $email;
    }

    private function resolveUsername(string $email): string
    {
        $username = $this->option('username');

        if (is_string($username) && $username !== '') {
            return $username;
        }

        $base = Str::slug(Str::before($email, '@')) ?: 'super-admin';
        $candidate = $base;

        while (User::where('username', $candidate)->exists()) {
            $candidate = $base.'-'.Str::random(6);
        }

        return $candidate;
    }

    private function resolvePassword(bool $userExists): ?string
    {
        $password = $this->option('password');

        if (is_string($password) && $password !== '') {
            return $password;
        }

        try {
            if ($userExists) {
                return null;
            }

            $password = trim((string) $this->secret('Mot de passe du super administrateur'));

            return $password !== '' ? $password : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function ensureRole(): Role
    {
        $role = Role::where('name', self::ROLE_NAME)->first();

        if ($role) {
            return $role;
        }

        $this->warn('Le rôle ['.self::ROLE_NAME.'] est absent : il vient d\'être créé.');

        return Role::create([
            'name' => self::ROLE_NAME,
            'display_name' => 'Administrator',
        ]);
    }

    private function ensureUser(string $name, string $username, string $email, ?string $password, ?User $existing = null): User
    {
        $user = $existing ?? new User([
            'name' => $name,
            'username' => $username,
            'email' => $email,
        ]);

        if ($existing) {
            $this->line('Utilisateur existant, mise à jour de l\'accès : '.$email);
            $user->name = $name !== '' ? $name : $user->name;
        }

        if ($password !== null) {
            $user->password = $password;
        }
        $user->active = true;
        $user->must_change_password = false;
        $user->email_verified_at = now();

        if (! $user->username) {
            $user->username = $username;
        }

        $user->save();

        return $user;
    }

    private function ensureUserRole(User $user, Role $role): void
    {
        $exists = DB::table('auth_user_roles')
            ->where('user_id', $user->id)
            ->where('role_id', $role->id)
            ->exists();

        if (! $exists) {
            DB::table('auth_user_roles')->insert([
                'user_id' => $user->id,
                'role_id' => $role->id,
            ]);
        }
    }

    /**
     * Attache toutes les permissions au rôle (mode additif par défaut,
     * reconstruction complète avec --reset-access).
     */
    private function ensureRolePermissions(Role $role): int
    {
        $permissionIds = DB::table('auth_permissions')->pluck('id')->all();

        if ($permissionIds === []) {
            $this->warn('Aucune permission en base — lancez d\'abord les seeders (php artisan db:seed).');

            return 0;
        }

        if ($this->option('reset-access')) {
            DB::table('auth_role_permissions')->where('role_id', $role->id)->delete();
            $existing = [];
        } else {
            $existing = DB::table('auth_role_permissions')
                ->where('role_id', $role->id)
                ->pluck('permission_id')
                ->all();
        }

        $missing = array_diff($permissionIds, $existing);

        foreach ($missing as $permissionId) {
            DB::table('auth_role_permissions')->insert([
                'role_id' => $role->id,
                'permission_id' => $permissionId,
            ]);
        }

        return count($permissionIds);
    }
}
