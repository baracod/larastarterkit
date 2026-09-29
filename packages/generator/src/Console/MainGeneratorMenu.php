<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Console;

use Baracod\Larastarterkit\Generator\Backend\ModuleGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\GenerationOrchestrator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Output\ConsoleOutput;

use function Laravel\Prompts\info;
use function Laravel\Prompts\note;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Menu principal amélioré pour le générateur CRUD.
 *
 * Offre une expérience utilisateur fluide avec :
 * - Sélection du module/modèle
 * - Affichage du statut de génération
 * - Boutons rapides pour générer Backend/Frontend/Fullstack
 * - Configuration détaillée du modèle
 */
final class MainGeneratorMenu
{
    private string $moduleName;

    private ?string $jsonPath;

    public function __construct(?string $moduleName = null, ?string $jsonPath = null)
    {
        if ($moduleName) {
            $this->moduleName = Str::studly($moduleName);
            $this->jsonPath = $jsonPath ?? base_path("Modules/{$this->moduleName}/module.json");
        } else {
            $this->moduleName = '';
            $this->jsonPath = null;
        }
    }

    /**
     * Lance le menu interactif du générateur CRUD.
     */
    public function start(): void
    {
        while (true) {
            $choice = select(
                label: '🚀 Générateur CRUD Laravel',
                options: [
                    'generate' => '⚡ Générer un CRUD',
                    'manage' => '📋 Gérer un module (modèles, champs, relations)',
                    'module' => '📦 Créer un module',
                    'ai' => '🤖 Assistance IA',
                    'help' => '❓ Aide',
                    'exit' => '❌ Quitter',
                ],
                default: 'generate'
            );

            if ($choice === 'exit') {
                return;
            }

            match ($choice) {
                'generate' => $this->generateFlow(),
                'manage' => $this->manageFlow(),
                'module' => $this->createModuleFlow(),
                'ai' => Artisan::call('larastarterkit:ai', [], new ConsoleOutput),
                'help' => $this->showHelp(),
                default => null,
            };
        }
    }

    /**
     * Flux: Générer un CRUD.
     */
    private function generateFlow(): void
    {
        // Sélection du module/modèle
        [$module, $modelKey] = $this->selectModuleModel();

        if (! $module || ! $modelKey) {
            warning('Génération annulée.');

            return;
        }

        // Orchestrateur
        try {
            $orchestrator = GenerationOrchestrator::for($module, $modelKey);

            // Menu de génération amélioré
            $builder = new GenerationMenuBuilder($orchestrator, $this->loadModel($module, $modelKey));
            $builder->show();

            info("\n✅ Génération terminée ! Relancez le serveur frontend si nécessaire.");
        } catch (\Exception $e) {
            warning("❌ Erreur: {$e->getMessage()}");
        }
    }

    /**
     * Flux: Gérer un module (édition, configuration).
     */
    private function manageFlow(): void
    {
        try {
            $ui = ConsoleUI::for($this->moduleName !== '' ? $this->moduleName : null);
            $ui->interactive();
        } catch (\Exception $e) {
            warning("❌ Erreur: {$e->getMessage()}");
        }
    }

    /**
     * Affiche l'aide.
     */
    private function createModuleFlow(): void
    {
        $name = text('Nom du module', placeholder: 'Inventory', required: true, validate: fn (string $v) => preg_match('/^[A-Za-z][A-Za-z0-9]{1,63}$/', $v) ? null : 'Lettres et chiffres uniquement.');
        $description = text('Description (menu des modules)', placeholder: 'Gestion des stocks');
        Artisan::call('larastarterkit:module', ['name' => $name, '--description' => $description ?: null], new ConsoleOutput);
    }

    private function showHelp(): void
    {
        note(implode("\n", [
            'Toutes les commandes du générateur : php artisan list larastarterkit',
            '',
            '  php artisan larastarterkit                       Ce menu',
            '  php artisan larastarterkit:module Inventory      Crée un module',
            '  php artisan larastarterkit:definition Inventory Product --fields="name:string:120, price:float"',
            '                                                   Définit une entité (ou --describe=… avec l’IA, --from-table=…)',
            '  php artisan larastarterkit:crud Inventory product --strategy=fullstack',
            '                                                   Génère (backend, frontend, fullstack ou --modes=…)',
            '  php artisan larastarterkit:ai --test             État et test de l’assistance IA',
            '',
            'Options utiles : --force (régénérer), --no-ai (règles uniquement), --no-interaction.',
            'Guide complet : vendor/baracod/larastarterkit-generator/docs/USAGE.md',
        ]));
    }

    /**
     * Sélection interactive du module et modèle.
     *
     * @return array{string,string} [module, modelKey]
     */
    private function selectModuleModel(): array
    {
        // Liste des modules
        $modules = ModuleGen::getModuleList();

        if (empty($modules)) {
            warning('Aucun module trouvé.');

            return ['', ''];
        }

        $moduleOptions = [];
        foreach ($modules as $m) {
            $moduleOptions[$m] = "📦 {$m}";
        }
        $moduleOptions['__create'] = '➕ Créer un module';

        $chosenModule = select(
            label: 'Sélectionner un module',
            options: $moduleOptions,
            default: array_key_first($moduleOptions),
            scroll: 15
        );

        if ($chosenModule === '__create') {
            warning('Création de module: non implémenté ici. Utilisez `php artisan make:module`');

            return ['', ''];
        }

        // Charger les modèles depuis le JSON du module
        $chosenModule = Str::studly($chosenModule);
        $jsonPath = base_path("Modules/{$chosenModule}/module.json");

        if (! File::exists($jsonPath)) {
            warning("Fichier de définition introuvable pour {$chosenModule}");

            return ['', ''];
        }

        try {
            $store = DefinitionStore::fromFile($jsonPath);
            $models = $store->module()->all();

            if (empty($models)) {
                warning("Aucun modèle défini dans {$chosenModule}");

                return ['', ''];
            }

            // Options: les modèles avec leur nom et table
            $modelOptions = [];
            foreach ($models as $key => $model) {
                $tableName = $model->tableName();
                $modelOptions[$key] = "📋 {$model->name()} ({$tableName})";
            }

            $chosenModelKey = select(
                label: 'Sélectionner le modèle',
                options: $modelOptions,
                default: array_key_first($modelOptions),
                scroll: 15
            );

            return [$chosenModule, $chosenModelKey];
        } catch (\Exception $e) {
            warning("❌ Erreur lors du chargement des modèles: {$e->getMessage()}");

            return ['', ''];
        }
    }

    /**
     * Charge le modèle depuis le JSON.
     */
    private function loadModel(string $module, string $modelKey)
    {
        $jsonPath = base_path("Modules/{$module}/module.json");

        if (! File::exists($jsonPath)) {
            throw new \RuntimeException("Fichier de définition introuvable: {$jsonPath}");
        }

        $store = DefinitionStore::fromFile($jsonPath);

        return $store->module()->model($modelKey);
    }
}
