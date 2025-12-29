<?php

namespace Baracod\Larastarterkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class LarastarterkitInstallCommand extends Command
{
    public $signature = 'larastarterkit:install';

    public $description = 'Install the Vue+Vuetify Admin Dashboard stack and publish configs';

    public function handle(): int
    {
        $this->alert('INSTALLATION DE LARASTARTERKIT');

        // 1. Configuration de Sanctum
        $this->installSanctum();

        // 2. Publication automatique des Configs et Stubs du package
        $this->publishPackageResources();

        // 3. Mise en place du dossier Modules et fichiers racines
        $this->setupModulesStructure();

        // 4. Configuration du Composer Merge Plugin
        $this->configureComposerMergePlugin();

        // 5. Publication des Assets Vue/Vuetify (Scaffolding)
        $this->installScaffolding();

        // 6. Gestion des dépendances NPM
        $this->updatePackageJson();

        // 7. Route SPA
        $this->installSpaRoute();

        $this->newLine();
        $this->info('✅ Installation terminée avec succès !');
        $this->comment('👉 Prochaine étape : lancez "npm install && npm run dev"');

        return self::SUCCESS;
    }

    protected function publishPackageResources()
    {
        $this->info('⚙️  Publication des fichiers de configuration...');

        // Publie config/larastarterkit.php (Géré par Spatie)
        $this->call('vendor:publish', [
            '--tag' => 'larastarterkit-config',
        ]);

        // Publie config/modules.php (Ton custom publish)
        $this->call('vendor:publish', [
            '--tag' => 'larastarterkit-modules-config',
        ]);

        // Publie les Stubs (Ton custom publish)
        $this->call('vendor:publish', [
            '--tag' => 'larastarterkit-stubs',
        ]);
    }

    protected function configureComposerMergePlugin()
    {
        $this->info('🔧 Configuration de composer.json (Merge Plugin)...');

        $composerPath = base_path('composer.json');

        if (! file_exists($composerPath)) {
            return;
        }

        $composer = json_decode(file_get_contents($composerPath), true);

        // 1. Ajouter wikimedia/composer-merge-plugin aux require si absent
        if (! isset($composer['require']['wikimedia/composer-merge-plugin'])) {
            $composer['require']['wikimedia/composer-merge-plugin'] = '^2.1';
        }

        // 2. Configurer allow-plugins
        $composer['config'] = $composer['config'] ?? [];
        $composer['config']['allow-plugins'] = $composer['config']['allow-plugins'] ?? [];
        $composer['config']['allow-plugins']['wikimedia/composer-merge-plugin'] = true;

        // 3. Configurer le bloc extra.merge-plugin
        $composer['extra'] = $composer['extra'] ?? [];
        $composer['extra']['merge-plugin'] = $composer['extra']['merge-plugin'] ?? [];

        $currentIncludes = $composer['extra']['merge-plugin']['include'] ?? [];
        if (! in_array('Modules/*/composer.json', $currentIncludes)) {
            $currentIncludes[] = 'Modules/*/composer.json';
            $composer['extra']['merge-plugin']['include'] = $currentIncludes;
        }

        file_put_contents(
            $composerPath,
            json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
        );

        $this->line('  - composer.json mis à jour.');
    }

    protected function _installScaffolding()
    {
        $this->info('📂 Copie de l\'architecture Vue/Vuetify...');

        $filesystem = new Filesystem;
        $stubPath = __DIR__.'/../../Stubs/frontend/scaffold';

        // Copie Resources
        if ($filesystem->exists($stubPath.'/resources')) {
            $filesystem->copyDirectory($stubPath.'/resources', resource_path());
        }

        // Copie Configs racine
        $filesToCopy = [
            'vite.config.ts',
            'tsconfig.json',
            'themeConfig.ts',
            'vite-module-loader.ts',
            'shims.d.ts',
        ];

        foreach ($filesToCopy as $file) {
            if (file_exists($stubPath.'/'.$file)) {
                copy($stubPath.'/'.$file, base_path($file));
                $this->line("  - $file copié.");
            }
        }
    }

    /**
     * Orchestrateur principal pour l'installation des fichiers de base.
     */
    protected function installScaffolding()
    {
        // 1. Installation du Backend (Configs Laravel, JSON modules, etc.)
        $this->installBackendScaffolding();

        // 2. Installation du Frontend (Vue, Vite, TS)
        $this->installFrontendScaffolding();
    }

    /**
     * Gère la copie des fichiers liés à l'architecture Frontend (Vue/Vuetify).
     */
    protected function installFrontendScaffolding()
    {
        $this->info('🎨 Copie de l\'architecture Frontend (Vue/Vuetify)...');

        $filesystem = new Filesystem;
        $stubPath = __DIR__.'/../../Stubs/frontend/scaffold';

        // 1. Copie du dossier Resources (Vue App)
        if ($filesystem->exists($stubPath.'/resources')) {
            // On utilise copyDirectory pour copier le dossier entier
            $filesystem->copyDirectory($stubPath.'/resources', resource_path());
            $this->line('  - Dossier resources/ mis à jour.');
        }

        // 2. Copie des fichiers de configuration racine (Vite, TS, etc.)
        $filesToCopy = [
            'vite.config.ts',
            'tsconfig.json',
            'themeConfig.ts',
            'vite-module-loader.ts',
            'shims.d.ts',
        ];

        foreach ($filesToCopy as $file) {
            $source = $stubPath.'/'.$file;
            $destination = base_path($file);

            if ($filesystem->exists($source)) {
                $filesystem->copy($source, $destination);
                $this->line("  - $file copié.");
            }
        }
    }

    /**
     * Gère la copie des fichiers liés au Backend et à la structure Laravel/Modules.
     */
    protected function installBackendScaffolding()
    {
        $this->info('⚙️  Copie de l\'architecture Backend...');

        $filesystem = new Filesystem;
        // Nouveau chemin pour les stubs backend
        $stubPath = dirname(__DIR__, 2). '/Stubs/backend/scaffold';

        // Liste des fichiers Backend à copier à la racine
        $filesToCopy = [
            'modules_statuses.json',
            // Tu pourras ajouter d'autres fichiers ici plus tard (ex: docker-compose.yml, phpunit.xml custom...)
        ];

        foreach ($filesToCopy as $file) {
            $source = $stubPath.'/'.$file;
            $destination = base_path($file);

            if ($filesystem->exists($source)) {
                // On vérifie si on doit écraser ou non.
                // Pour modules_statuses.json, on préfère souvent ne pas écraser si l'utilisateur a déjà activé/désactivé des modules.
                if (! $filesystem->exists($destination)) {
                    $filesystem->copy($source, $destination);
                    $this->line("  - $file copié à la racine.");
                } else {
                    $this->line("  - $file existe déjà, ignoré.");
                }
            } else {
                // Optionnel : Warning si le stub manque (utile pour le dev)
                // $this->warn("  ⚠️ Stub backend introuvable : $file");
            }
        }

    }

    protected function updatePackageJson()
    {
        $this->info('📦 Mise à jour de package.json...');
        // Note: Assure-toi que la méthode mergePackageJson est bien présente dans ta classe (je l'ai condensée ici pour la lisibilité)
        $this->mergePackageJson(__DIR__.'/../../Stubs/frontend/scaffold/package.json');
    }

    protected function mergePackageJson($stubPackagePath)
    {
        if (! file_exists($stubPackagePath)) {
            return;
        }

        $stubPackages = json_decode(file_get_contents($stubPackagePath), true);
        $appPackagesPath = base_path('package.json');
        $appPackages = file_exists($appPackagesPath)
            ? json_decode(file_get_contents($appPackagesPath), true)
            : ['devDependencies' => [], 'dependencies' => []];

        $appPackages['devDependencies'] = array_merge(
            $appPackages['devDependencies'] ?? [],
            $stubPackages['devDependencies'] ?? []
        );

        $appPackages['dependencies'] = array_merge(
            $appPackages['dependencies'] ?? [],
            $stubPackages['dependencies'] ?? []
        );

        ksort($appPackages['devDependencies']);
        ksort($appPackages['dependencies']);

        file_put_contents(
            $appPackagesPath,
            json_encode($appPackages, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
        );
    }

    protected function setupModulesStructure()
    {
        $this->info('📂 Configuration du répertoire Modules...');

        $filesystem = new Filesystem;
        $modulesPath = base_path('Modules');
        // Assure-toi que ce chemin pointe bien vers le dossier parent contenant "Auth", "modules.json", etc.
        $stubPath = __DIR__.'/../../Stubs/frontend';

        // 1. Création du dossier racine Modules s'il n'existe pas
        if (! $filesystem->exists($modulesPath)) {
            $filesystem->makeDirectory($modulesPath, 0755, true);
            $this->line('  - Répertoire Modules/ créé.');
        }

        // ---------------------------------------------------------
        // 2. Copie des FICHIERS (modules.json, menuItems.ts, etc.)
        // ---------------------------------------------------------
        $filesToCopy = [
            'modules.json',
            'menuItems.ts',
        ];

        foreach ($filesToCopy as $file) {
            $source = $stubPath.'/'.$file;
            $destination = $modulesPath.'/'.$file;

            if (! $filesystem->exists($source)) {
                $this->warn("  ⚠️  Fichier Stub non trouvé : $source");

                continue;
            }

            if ($filesystem->exists($destination)) {
                $this->line("  - Fichier $file existe déjà, ignoré.");

                continue;
            }

            $filesystem->copy($source, $destination);
            $this->line("  - Fichier $file copié.");
        }

        // ---------------------------------------------------------
        // 3. Copie des DOSSIERS (Modules de base comme Auth)
        // ---------------------------------------------------------
        $modulesToCopy = [
            'Auth',
        ];

        foreach ($modulesToCopy as $moduleFolderName) {
            $source = __DIR__.'/../../Modules/'.$moduleFolderName;
            $destination = $modulesPath.'/'.$moduleFolderName;

            if (! $filesystem->exists($source)) {
                $this->warn("  ⚠️  Dossier Stub non trouvé : $source");

                continue;
            }

            if ($filesystem->exists($destination)) {
                $this->line("  - Module $moduleFolderName existe déjà, ignoré.");

                continue;
            }

            // CORRECTION ICI : Utilisation de copyDirectory pour les dossiers
            $filesystem->copyDirectory($source, $destination);
            $this->line("  - Module $moduleFolderName installé avec succès.");
        }
    }

    protected function installSpaRoute()
    {
        $routeContent = "\nRoute::get('/{any}', function () {\n    return view('application');\n})->where('any', '.*');\n";
        $webRoutesPath = base_path('routes/web.php');

        if (file_exists($webRoutesPath)) {
            $content = file_get_contents($webRoutesPath);
            if (! str_contains($content, "view('application')")) {
                file_put_contents($webRoutesPath, $routeContent, FILE_APPEND);
                $this->info('🔗 Route SPA ajoutée à routes/web.php');
            }
        }
    }

    protected function installSanctum()
    {
        $this->info('🔒 Configuration de Laravel Sanctum...');
        $this->call('vendor:publish', ['--provider' => 'Laravel\Sanctum\SanctumServiceProvider']);

        if ($this->confirm('Voulez-vous exécuter les migrations maintenant ?', true)) {
            $this->call('migrate');
        }
    }
}
