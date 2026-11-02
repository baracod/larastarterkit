<?php

namespace Tests\Feature;

use Baracod\Larastarterkit\Core\Support\ModuleTranslations;
use Illuminate\Support\Facades\File;
use Modules\Auth\Models\User;
use Tests\TestCase;

class CoreMessagesTranslationTest extends TestCase
{
    public function test_login_errors_follow_the_application_locale(): void
    {
        $user = User::factory()->create(['password' => 'password', 'active' => true]);

        app()->setLocale('fr');
        $this->login($user->email, 'wrong-password')->assertUnauthorized()->assertJsonPath('message', 'Identifiants invalides');

        app()->setLocale('en');
        $this->login($user->email, 'wrong-password')->assertUnauthorized()->assertJsonPath('message', 'Invalid credentials');
    }

    public function test_application_can_redefine_a_core_message_in_lang_vendor(): void
    {
        $lang = $this->temporaryDirectory();
        File::ensureDirectoryExists("{$lang}/vendor/auth/fr");
        File::put("{$lang}/vendor/auth/fr/messages.php", "<?php return ['invalid_credentials' => 'Adresse ou mot de passe incorrect.'];");
        app('translator')->getLoader()->addPath($lang);
        app()->setLocale('fr');
        $user = User::factory()->create(['password' => 'password', 'active' => true]);

        $this->login($user->email, 'wrong-password')->assertJsonPath('message', 'Adresse ou mot de passe incorrect.');
        $this->assertSame('Suppression effectuée.', __('auth::messages.deleted'));
    }

    public function test_legacy_application_directory_replaces_keys_without_hiding_new_ones(): void
    {
        $module = $this->temporaryDirectory();
        $legacy = $this->temporaryDirectory();
        File::ensureDirectoryExists("{$module}/fr");
        File::ensureDirectoryExists("{$legacy}/fr");
        File::put("{$module}/fr/messages.php", "<?php return ['kept' => 'Clé du module', 'changed' => 'Texte du module'];");
        File::put("{$legacy}/fr/messages.php", "<?php return ['changed' => 'Texte de l’application'];");
        File::put("{$legacy}/fr/extra.php", "<?php return ['only' => 'Groupe de l’application'];");

        ModuleTranslations::register(app(), 'legacydemo', $module, $legacy);

        $this->assertSame('Clé du module', trans('legacydemo::messages.kept', [], 'fr'));
        $this->assertSame('Texte de l’application', trans('legacydemo::messages.changed', [], 'fr'));
        $this->assertSame('Groupe de l’application', trans('legacydemo::extra.only', [], 'fr'));
    }

    public function test_every_core_message_exists_in_french_and_english(): void
    {
        foreach (['auth::messages', 'admin::messages'] as $group) {
            $french = array_keys(trans($group, [], 'fr'));
            $english = array_keys(trans($group, [], 'en'));

            $this->assertNotEmpty($french, $group);
            $this->assertEqualsCanonicalizing($french, $english, $group);
        }
    }

    private function temporaryDirectory(): string
    {
        $directory = sys_get_temp_dir().'/lsk-lang-'.uniqid();
        File::ensureDirectoryExists($directory);
        $this->beforeApplicationDestroyed(fn () => File::deleteDirectory($directory));

        return $directory;
    }

    private function login(string $email, string $password)
    {
        config(['app.url' => 'http://localhost']);

        return $this->withHeader('Origin', 'http://localhost')
            ->postJson('/api/v1/auth/login', ['email' => $email, 'password' => $password]);
    }
}
