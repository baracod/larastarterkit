<?php

namespace Baracod\Larastarterkit\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;

class LarastarterkitInstallCommand extends Command
{
    public $signature = 'larastarterkit:install
                        {--force : Force overwrite existing files without confirmation}
                        {--skip-migration : Skip database migrations}
                        {--dry-run : Show what would be done without making changes}';

    public $description = 'Install the Vue+Vuetify Admin Dashboard stack and publish configs';

    private bool $composerModified = false;

    private array $backups = [];

    public function handle(): int
    {
        $this->alert('INSTALLATION DE LARASTARTERKIT');

        if ($this->option('dry-run')) {
            $this->warn('🔍 Mode DRY-RUN activé - Aucune modification ne sera effectuée');
        }

        try {
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
            $this->displayPostInstallMessages();

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('❌ Erreur durant l\'installation : ' . $e->getMessage());
            $this->rollbackChanges();
            return self::FAILURE;
        }
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
            $this->warn('  ⚠️  composer.json introuvable, ignoré.');
            return;
        }

        if ($this->option('dry-run')) {
            $this->line('  [DRY-RUN] composer.json serait modifié');
            return;
        }

        $content = file_get_contents($composerPath);
        $composer = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('  ❌ Erreur de lecture composer.json : ' . json_last_error_msg());
            throw new \RuntimeException('composer.json invalide');
        }

        // Backup avant modification
        $this->createBackup($composerPath);

        $modified = false;

        // 1. Ajouter wikimedia/composer-merge-plugin aux require si absent
        if (! isset($composer['require']['wikimedia/composer-merge-plugin'])) {
            $composer['require']['wikimedia/composer-merge-plugin'] = '^2.1';
            $modified = true;
        }

        // 2. Configurer allow-plugins
        $composer['config'] = $composer['config'] ?? [];
        $composer['config']['allow-plugins'] = $composer['config']['allow-plugins'] ?? [];
        if (! isset($composer['config']['allow-plugins']['wikimedia/composer-merge-plugin'])) {
            $composer['config']['allow-plugins']['wikimedia/composer-merge-plugin'] = true;
            $modified = true;
        }

        // 3. Configurer le bloc extra.merge-plugin
        $composer['extra'] = $composer['extra'] ?? [];
        $composer['extra']['merge-plugin'] = $composer['extra']['merge-plugin'] ?? [];

        $currentIncludes = $composer['extra']['merge-plugin']['include'] ?? [];
        if (! in_array('Modules/*/composer.json', $currentIncludes)) {
            $currentIncludes[] = 'Modules/*/composer.json';
            $composer['extra']['merge-plugin']['include'] = $currentIncludes;
            $modified = true;
        }

        if ($modified) {
            file_put_contents(
                $composerPath,
                json_encode($composer, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT).PHP_EOL
            );
            $this->composerModified = true;
            $this->line('  ✅ composer.json mis à jour.');
        } else {
            $this->line('  ℹ️  composer.json déjà configuré.');
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

        if ($this->option('dry-run')) {
            $this->line('  [DRY-RUN] Fichiers frontend seraient copiés');
            return;
        }

        // 1. Copie du dossier Resources (Vue App) avec confirmation
        if ($filesystem->exists($stubPath.'/resources')) {
            $resourcesPath = resource_path();
            $shouldCopy = true;

            if ($filesystem->exists($resourcesPath) && ! $this->option('force')) {
                $shouldCopy = $this->confirm(
                    '⚠️  Le dossier resources/ existe déjà. Voulez-vous l\'écraser ?',
                    false
                );
            }

            if ($shouldCopy) {
                if ($filesystem->exists($resourcesPath)) {
                    $this->createBackup($resourcesPath, true);
                }
                $filesystem->copyDirectory($stubPath.'/resources', $resourcesPath);
                $this->line('  ✅ Dossier resources/ mis à jour.');
            } else {
                $this->line('  ⏭️  resources/ ignoré.');
            }
        }

        // 2. Gestion de jsconfig.json -> tsconfig.json
        $jsconfigPath = base_path('jsconfig.json');
        if ($filesystem->exists($jsconfigPath) && ! $filesystem->exists(base_path('tsconfig.json'))) {
            if ($this->confirm('Remplacer jsconfig.json par tsconfig.json ?', true)) {
                $this->createBackup($jsconfigPath);
                $filesystem->delete($jsconfigPath);
                $this->line('  ✅ jsconfig.json supprimé (remplacé par tsconfig.json).');
            }
        }

        // 3. Gestion de vite.config.js -> vite.config.ts
        $viteConfigJsPath = base_path('vite.config.js');
        if ($filesystem->exists($viteConfigJsPath)) {
            $shouldDelete = $this->option('force') || $this->confirm(
                '⚠️  vite.config.js détecté. Supprimer pour utiliser vite.config.ts ?',
                true
            );

            if ($shouldDelete) {
                $this->createBackup($viteConfigJsPath);
                $filesystem->delete($viteConfigJsPath);
                $this->line('  ✅ vite.config.js supprimé (remplacé par vite.config.ts).');
            } else {
                $this->warn('  ⚠️  vite.config.js conservé. Conflit possible avec vite.config.ts.');
            }
        }

        // 4. Copie des fichiers de configuration racine (Vite, TS, etc.)
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

            if (! $filesystem->exists($source)) {
                $this->warn("  ⚠️  Fichier stub manquant : $file");
                continue;
            }

            $shouldCopy = true;
            if ($filesystem->exists($destination) && ! $this->option('force')) {
                $shouldCopy = $this->confirm(
                    "⚠️  $file existe déjà. Écraser ?",
                    false
                );
            }

            if ($shouldCopy) {
                if ($filesystem->exists($destination)) {
                    $this->createBackup($destination);
                }
                $filesystem->copy($source, $destination);
                $this->line("  ✅ $file copié.");
            } else {
                $this->line("  ⏭️  $file ignoré.");
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
        $stubPath = dirname(__DIR__, 2).'/Stubs/backend/scaffold';
        $stubRootPath = dirname(__DIR__, 2).'/Stubs/backend';

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

        // Copier le DatabaseSeeder
        $databaseSeederStub = $stubRootPath.'/DatabaseSeeder.stub';
        $databaseSeederDest = database_path('seeders/DatabaseSeeder.php');

        if ($filesystem->exists($databaseSeederStub)) {
            $shouldCopy = true;

            if ($filesystem->exists($databaseSeederDest) && !$this->option('force')) {
                $shouldCopy = $this->confirm(
                    '⚠️  DatabaseSeeder.php existe déjà. Voulez-vous l\'écraser ?',
                    false
                );
            }

            if ($shouldCopy) {
                if ($filesystem->exists($databaseSeederDest)) {
                    $this->createBackup($databaseSeederDest);
                }
                $filesystem->copy($databaseSeederStub, $databaseSeederDest);
                $this->line("  ✅ DatabaseSeeder.php mis à jour.");
            } else {
                $this->line("  ⏭️  DatabaseSeeder.php ignoré.");
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
            $this->warn('  ⚠️  package.json stub introuvable.');
            return;
        }

        if ($this->option('dry-run')) {
            $this->line('  [DRY-RUN] package.json serait modifié');
            return;
        }

        $stubContent = file_get_contents($stubPackagePath);
        $stubPackages = json_decode($stubContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('  ❌ Stub package.json invalide : ' . json_last_error_msg());
            return;
        }

        $appPackagesPath = base_path('package.json');
        $appPackages = ['devDependencies' => [], 'dependencies' => []];

        if (file_exists($appPackagesPath)) {
            $this->createBackup($appPackagesPath);
            $appContent = file_get_contents($appPackagesPath);
            $decoded = json_decode($appContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('  ❌ package.json invalide : ' . json_last_error_msg());
                return;
            }
            $appPackages = $decoded;
        }

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

        $this->line('  ✅ package.json mis à jour.');
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

        // Exécuter composer dump-autoload pour enregistrer les nouveaux modules
        if (!$this->option('dry-run')) {
            $this->info('🔄 Régénération de l\'autoloader Composer...');
            exec('composer dump-autoload', $output, $returnCode);
            if ($returnCode === 0) {
                $this->line('  ✅ Autoloader Composer régénéré.');
            } else {
                $this->warn('  ⚠️  Erreur lors de la régénération de l\'autoloader.');
            }
        }
    }

    protected function installSpaRoute()
    {
        $webRoutesPath = base_path('routes/web.php');

        if (!file_exists($webRoutesPath)) {
            $this->warn('  ⚠️  routes/web.php introuvable.');
            return;
        }

        $content = file_get_contents($webRoutesPath);

        // Vérifier si la route SPA n'existe pas déjà
        if (str_contains($content, "view('application')")) {
            $this->line('  ℹ️  Route SPA déjà présente dans routes/web.php');
            return;
        }

        $routeContent = "\nRoute::get('/{any}', function () {\n    return view('application');\n})->where('any', '.*');\n";

        // Trouver la position après le dernier 'use' statement
        $lines = explode("\n", $content);
        $lastUseIndex = -1;

        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (preg_match('/^use\s+/', $trimmedLine)) {
                $lastUseIndex = $index;
            }
        }

        // Si on a trouvé des 'use' statements, insérer après
        if ($lastUseIndex >= 0) {
            // Insérer après le dernier use (avec une ligne vide)
            array_splice($lines, $lastUseIndex + 1, 0, [$routeContent]);
            $newContent = implode("\n", $lines);
        } else {
            // Sinon, ajouter à la fin du fichier
            $newContent = rtrim($content) . $routeContent;
        }

        file_put_contents($webRoutesPath, $newContent);
        $this->info('🔗 Route SPA ajoutée à routes/web.php');
    }

    protected function installSanctum()
    {
        $this->info('🔒 Configuration de Laravel Sanctum...');

        if ($this->option('dry-run')) {
            $this->line('  [DRY-RUN] Sanctum serait publié et migré');
            return;
        }

        $this->call('vendor:publish', ['--provider' => 'Laravel\Sanctum\SanctumServiceProvider']);

        if (! $this->option('skip-migration')) {
            $this->line('  ⏳ Exécution des migrations...');
            $this->call('migrate', ['--force' => true]);
        } else {
            $this->line('  ⏭️  Migrations ignorées (--skip-migration).');
        }
    }

    /**
     * Créer un backup d'un fichier ou dossier
     */
    protected function createBackup(string $path, bool $isDirectory = false): void
    {
        $filesystem = new Filesystem;
        $backupPath = $path . '.backup.' . date('YmdHis');

        if ($isDirectory) {
            $filesystem->copyDirectory($path, $backupPath);
        } else {
            $filesystem->copy($path, $backupPath);
        }

        $this->backups[] = $backupPath;
        $this->line("  💾 Backup créé : $backupPath");
    }

    /**
     * Restaurer les fichiers en cas d'erreur
     */
    protected function rollbackChanges(): void
    {
        if (empty($this->backups)) {
            return;
        }

        $this->warn('🔄 Rollback des modifications...');
        $filesystem = new Filesystem;

        foreach ($this->backups as $backupPath) {
            $originalPath = preg_replace('/\.backup\.\d+$/', '', $backupPath);

            if ($filesystem->exists($backupPath)) {
                if ($filesystem->isDirectory($backupPath)) {
                    if ($filesystem->exists($originalPath)) {
                        $filesystem->deleteDirectory($originalPath);
                    }
                    $filesystem->copyDirectory($backupPath, $originalPath);
                } else {
                    $filesystem->copy($backupPath, $originalPath);
                }
                $this->line("  ✅ Restauré : $originalPath");
            }
        }
    }

    /**
     * Afficher les messages post-installation
     */
    protected function displayPostInstallMessages(): void
    {
        $this->newLine();

        if ($this->composerModified) {
            $this->warn('⚠️  composer.json a été modifié.');
            $this->comment('   Exécutez : composer update');
            $this->newLine();
        }

        $this->comment('👉 Prochaines étapes :');
        $this->line('   1. npm install');
        $this->line('   2. npm run dev');

        if ($this->option('skip-migration')) {
            $this->newLine();
            $this->warn('⚠️  Migrations ignorées. Pensez à exécuter : php artisan migrate');
        }
    }
}
