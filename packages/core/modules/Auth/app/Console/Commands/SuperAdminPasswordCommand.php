<?php

namespace Modules\Auth\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Modules\Auth\Models\User;

class SuperAdminPasswordCommand extends Command
{
    protected $signature = 'auth:super-admin:password
        {--email= : Email du super administrateur existant}
        {--password= : Nouveau mot de passe (saisie masquée sinon)}';

    protected $description = 'Initialise ou réinitialise le mot de passe d’un super administrateur';

    public function handle(): int
    {
        $email = trim((string) $this->option('email'));
        if ($email === '' && $this->input->isInteractive()) {
            $email = trim((string) $this->ask('Email du super administrateur'));
        }

        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Un email valide est requis (option --email).');

            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if (! $user || ! $user->hasRole('administrator')) {
            $this->error('Aucun super administrateur ne correspond à cet email.');

            return self::FAILURE;
        }

        $password = $this->option('password');
        if ((! is_string($password) || $password === '') && $this->input->isInteractive()) {
            $password = $this->secret('Nouveau mot de passe du super administrateur');
            $confirmation = $this->secret('Confirmez le nouveau mot de passe');
            if ($password !== $confirmation) {
                $this->error('Les mots de passe ne correspondent pas.');

                return self::FAILURE;
            }
        }

        $validator = Validator::make(['password' => $password], [
            'password' => ['required', 'string', 'max:72', Password::min(8)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails() || strlen((string) $password) > 72) {
            $this->error('Le mot de passe doit contenir au moins 8 caractères, une majuscule, une minuscule, un chiffre et un symbole (72 octets maximum).');

            return self::FAILURE;
        }

        DB::transaction(function () use ($user, $password): void {
            $user = User::query()->lockForUpdate()->findOrFail($user->id);
            // Recheck the role after acquiring the lock, before changing any credentials.
            abort_unless($user->hasRole('administrator'), 403, 'Ce compte n’est plus super administrateur.');
            $user->forceFill([
                'password' => $password,
                'must_change_password' => false,
                'password_changed_at' => now(),
                'remember_token' => Str::random(60),
            ])->save();
            $user->tokens()->delete();
            DB::table('auth_password_reset_tokens')->where('email', $user->email)->delete();

            if (config('session.driver') === 'database') {
                DB::connection(config('session.connection'))
                    ->table(config('session.table'))
                    ->where('user_id', $user->id)->delete();
            }
        });

        $this->info('Mot de passe du super administrateur initialisé.');

        return self::SUCCESS;
    }
}
