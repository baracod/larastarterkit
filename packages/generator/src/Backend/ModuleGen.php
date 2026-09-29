<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend;

use Baracod\Larastarterkit\Generator\Backend\Database\PermissionSeederGen;
use Baracod\Larastarterkit\Generator\Helpers\OptimizationManager;
use Baracod\Larastarterkit\Generator\Support\ModuleAutoload;
use Baracod\Larastarterkit\Generator\Utils\ConsoleTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Laravel\Module as LaravelModule;
use Nwidart\Modules\Module as ClsModule;
use RuntimeException;

use function Laravel\Prompts\note;
use function Laravel\Prompts\select;

/**
 * Class ModuleGen
 *
 * Gère la création, l’inspection et la suppression d’un module NWIDART,
 * ainsi que quelques opérations associées (routes, permissions, etc.).
 *
 * 👉 Le constructeur n’a plus d’effets de bord : il ne génère rien.
 *    Utilise generate(), ensureExists() ou promptAndGenerateIfMissing().
 */
class ModuleGen
{
    use ConsoleTrait;

    /** @var string Nom StudlyCase du module (clé NWIDART) */
    private string $moduleName;

    /** @var string Nom en minuscules (préfixe table, etc.) */
    private string $moduleKey;

    /** @var ClsModule|null Instance NWIDART du module s’il existe */
    private ?ClsModule $module = null;

    /** @var string|null Icône du module (métadonnée UI) */
    private ?string $icon = null;

    /** @var string|null Auteur (métadonnée) */
    private ?string $author = null;

    /** @var string|null Description (métadonnée) */
    private ?string $description = null;

    /** @var string|null Groupe/catégorie (métadonnée) */
    private ?string $groupe = null;

    /** @var array<int,string> Liste des tables liées (préfixées par moduleKey_) */
    private array $tables = [];

    /**
     * Constructeur — ne crée pas le module.
     *
     * @param  string  $name  Nom “humain” du module (ex: "Blog" ou "blog")
     * @param  string|null  $icon  Icône optionnelle
     * @param  string|null  $author  Auteur optionnel
     * @param  string|null  $description  Description optionnelle
     * @param  string|null  $groupe  Groupe/catégorie optionnelle
     *
     * @throws \InvalidArgumentException Si le nom est vide
     */
    public function __construct(
        string $name,
        ?string $icon = null,
        ?string $author = null,
        ?string $description = null,
        ?string $groupe = null
    ) {
        if (trim($name) === '') {
            throw new \InvalidArgumentException('Le nom du module est requis.');
        }

        $this->icon = $icon;
        $this->author = $author;
        $this->description = $description;
        $this->groupe = $groupe;

        // Normalisations
        $this->moduleName = Str::studly($name);
        $this->moduleKey = Str::lower($name);

        $this->refreshModuleRef();
        $this->initTables();
    }

    /**
     * Recharge la référence NWIDART du module (si présent).
     */
    private function refreshModuleRef(): void
    {
        try {
            $this->module = Module::find($this->moduleName) ?: null;
        } catch (\Throwable) {
            $this->module = null;
        }
    }

    /**
     * Récupère les tables dont le nom commence par "{$moduleKey}_".
     */
    private function initTables(): void
    {
        $prefix = $this->moduleKey.'_';
        $this->tables = array_values(array_filter(
            Schema::getTableListing(),
            static fn (string $table) => Str::startsWith($table, $prefix)
        ));
    }

    /**
     * Liste les modules activés (noms StudlyCase).
     *
     * @return array<int,string>
     */
    public static function getModuleList(): array
    {
        /** @var array<string, LaravelModule> $enabled */
        $enabled = Module::allEnabled();

        // Les clés du tableau sont généralement les noms StudlyCase
        return array_values(array_map(
            static fn (string $key) => Str::studly($key),
            array_keys($enabled)
        ));
    }

    /**
     * Retourne la liste des tables liées au module (préfixées).
     *
     * @return array<int,string>
     */
    public function getTableList(): array
    {
        return $this->tables;
    }

    /**
     * Indique si le module existe (dans NWIDART).
     */
    public function exists(): bool
    {
        try {
            return Module::has($this->moduleName);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Assure l’existence du module. Si manquant, lance generate().
     *
     * @param  bool  $refreshCaches  Rafraîchir caches/composer après génération
     * @return $this
     */
    public function ensureExists(bool $refreshCaches = true): self
    {
        if (! $this->exists()) {
            $this->generate(refreshCaches: $refreshCaches);
        }

        return $this;
    }

    /**
     * Variante interactive : si manquant, propose la création.
     *
     * @return $this
     *
     * @throws RuntimeException Si l’utilisateur refuse la création
     */
    public function promptAndGenerateIfMissing(bool $refreshCaches = true): self
    {
        if ($this->exists()) {
            return $this;
        }

        $answer = select(
            "Le module {$this->moduleName} n'existe pas. Voulez-vous le créer ?",
            ['oui', 'non'],
            'oui'
        );

        if ($answer !== 'oui') {
            throw new RuntimeException("Le module '{$this->moduleName}' n'existe pas.");
        }

        note("Génération du module {$this->moduleName}…", 'info');
        $this->generate(refreshCaches: $refreshCaches);
        note("Module {$this->moduleName} généré avec succès.", 'success');

        return $this;
    }

    /**
     * Génère le module via Artisan (nwidart/module:make) si absent,
     * met à jour modules.json, permissions, et rafraîchit les caches.
     *
     * @param  bool  $refreshCaches  Rafraîchir composer+optimize après génération
     * @return $this
     *
     * @throws RuntimeException Si l’exécution Artisan échoue
     */
    public function generate(bool $refreshCaches = true): self
    {
        if ($this->exists()) {
            $this->refreshModuleRef();

            return $this;
        }

        // 1) Créer le module (NWIDART)
        // module:make attend une liste de noms ; une chaîne était ignorée sans erreur.
        $code = Artisan::call('module:make', ['name' => [$this->moduleName], '--no-interaction' => true]);
        if ($code !== 0 || ! File::exists(base_path("Modules/{$this->moduleName}/module.json"))) {
            throw new RuntimeException("Échec de 'module:make {$this->moduleName}' (code {$code}) : ".trim(Artisan::output()));
        }

        // 2) Autoload Composer (classes du module, factories, seeders) + permissions d'accès (seeder + base)
        ModuleAutoload::register($this->moduleName);
        $this->generatePermissions();

        // 3) Enrichir Modules/modules.json
        $this->appendToModulesJson();

        // 4) Rafraîchir l’instance NWIDART
        $this->refreshModuleRef();

        // 5) Rafraîchir caches si demandé
        if ($refreshCaches) {
            if (class_exists(OptimizationManager::class)) {
                OptimizationManager::refreshAll(withComposer: true, rebuild: app()->environment('production'));
            } else {
                // fallback minimal
                Artisan::call('optimize:clear');
            }
        }

        return $this;
    }

    /**
     * Ajoute l’entrée du module dans Modules/modules.json (idempotent).
     */
    private function appendToModulesJson(): void
    {
        $path = base_path('Modules/modules.json');

        // Même forme que les entrées existantes (type Module du frontend) ; "module" relie l'entrée à son statut d'activation.
        $payload = array_filter([
            'icon' => $this->icon ?: 'mdi-puzzle-outline',
            'title' => $this->moduleName,
            'description' => $this->description ?: null,
            'action' => 'access',
            'subject' => Str::lower($this->moduleName),
            'to' => ['name' => Str::lower($this->moduleName)],
            'module' => $this->moduleName,
        ], static fn ($value) => $value !== null);

        $list = [];
        if (File::exists($path)) {
            $decoded = json_decode((string) File::get($path), true);
            if (is_array($decoded)) {
                $list = array_values($decoded);
            }
        }
        if (collect($list)->firstWhere('title', $this->moduleName)) {
            return;
        }
        $list[] = $payload;

        // Indentation de 2 espaces, comme le fichier d'origine (évite de reformater les entrées existantes).
        $json = (string) json_encode($list, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $json = (string) preg_replace_callback('/^( {4})+/m', static fn (array $m) => str_repeat('  ', strlen($m[0]) / 4), $json);
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $json.PHP_EOL);
    }

    /**
     * Permission "access_{module}" : écrite dans le seeder du module et appliquée à la base courante.
     */
    public function generatePermissions(): void
    {
        $result = (new PermissionSeederGen($this->moduleName))->generate();
        $this->consoleWriteSuccess("✅ Permissions du module `{$this->moduleName}` prêtes (".basename($result['path']).').');
    }

    /**
     * Supprime le module et son entrée modules.json (après confirmation explicite).
     *
     * @param  bool  $confirmation  Doit être true pour procéder
     * @return bool True si la suppression NWIDART a réussi
     *
     * @throws \Throwable En cas d’erreur d’E/S
     */
    public function delete(bool $confirmation = false): bool
    {
        if (! $confirmation) {
            return false;
        }

        app(\Baracod\Larastarterkit\Core\Support\ModuleRegistry::class)->assertLocal($this->moduleName);
        // 1) Nettoyer Modules/modules.json
        $path = base_path('Modules/modules.json');
        if (File::exists($path)) {
            $items = json_decode((string) File::get($path), true) ?: [];
            $items = array_values(array_filter(
                $items,
                fn (array $it) => ($it['title'] ?? null) !== $this->moduleName
            ));
            File::put($path, json_encode($items, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        }

        // 2) Supprimer le module NWIDART
        $ok = Module::delete($this->moduleName);
        $this->refreshModuleRef();

        return (bool) $ok;
    }

    /**
     * Namespace racine du module (ex: "Modules\Blog").
     *
     *
     * @throws RuntimeException Si le module n’existe pas
     */
    public function getNameSpace(): string
    {
        if (! $this->moduleName) {
            throw new RuntimeException('Nom du module non initialisé.');
        }

        return 'Modules\\'.$this->moduleName;
    }

    /**
     * Chemin absolu du module, ou sous-dossier si $relativePath est fourni.
     *
     *
     * @throws RuntimeException Si le module n’existe pas
     */
    public function getPath(?string $relativePath = null): string
    {
        $this->refreshModuleRef();

        if (! $this->module) {
            throw new RuntimeException("Le module '{$this->moduleName}' n'existe pas.");
        }

        return $relativePath
            ? $this->module->getPath().'/'.ltrim($relativePath, '/')
            : $this->module->getPath();
    }

    public function getControllerNameSpace(): string
    {
        return $this->getNameSpace().'\\Http\\Controllers';
    }

    public function getRequestNamespace(): string
    {
        return $this->getNameSpace().'\\Http\\Requests';
    }

    public function getPathControllers(): string
    {
        return $this->getPath('app/Http/Controllers');
    }

    public function getModelNameSpace(): string
    {
        return $this->getNameSpace().'\\Models';
    }

    public function getModelsDirectoryPath(): string
    {
        return $this->getPath('app/Models');
    }

    /**
     * Indique si un modèle (fichier PHP) existe dans app/Models.
     *
     * @param  string  $modelName  Nom de classe (ex: "BlogAuthor")
     */
    public function modelExist(string $modelName): bool
    {
        $path = $this->getModelsDirectoryPath().'/'.$modelName.'.php';

        return File::exists($path);
    }

    /**
     * Retourne le chemin du fichier d’un modèle, s’il existe.
     */
    public function getModelPath(string $modelName): ?string
    {
        $path = $this->getModelsDirectoryPath().'/'.$modelName.'.php';

        return File::exists($path) ? $path : null;
    }

    /**
     * Liste des modèles (noms de fichiers sans .php) dans app/Models.
     *
     * @return array<int,string>|null
     */
    public function getModels(): ?array
    {
        $dir = $this->getModelsDirectoryPath();

        if (! File::exists($dir)) {
            return null;
        }

        $files = collect(File::files($dir))
            ->filter(fn ($f) => Str::endsWith($f->getFilename(), '.php'))
            ->map(fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME))
            ->values()
            ->all();

        return $files ?: null;
    }

    public function getRoutePath(): string
    {
        return $this->getPath('routes');
    }

    public function getRouteApiPath(): string
    {
        return $this->getRoutePath().'/api.php';
    }

    public function getRouteWebPath(): string
    {
        return $this->getRoutePath().'/web.php';
    }

    /**
     * Injecte une ligne de route dans api.php avant le marqueur //{{ next-route }}.
     *
     * @param  string  $route  Ligne à insérer (doit contenir le ";")
     *
     * @throws RuntimeException Si lecture/écriture échoue
     */
    public function generateRoute(string $route): void
    {
        $path = $this->getRouteApiPath();
        $content = @file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Impossible de lire le fichier {$path}");
        }

        $marker = '//{{ next-route }}';
        if (! str_contains($content, $marker)) {
            throw new RuntimeException("Marqueur '{$marker}' introuvable dans {$path}");
        }

        // On insère la route AVANT le marqueur pour permettre des insertions multiples
        $updated = str_replace($marker, rtrim($route).PHP_EOL.'    '.$marker, $content);

        if (@file_put_contents($path, $updated) === false) {
            throw new RuntimeException("Impossible d'écrire dans le fichier {$path}");
        }
    }

    /**
     * Retourne (ou crée) un ModuleGenerator basé sur le préfixe table "prefix_table".
     */
    public function getModuleOfTable(string $table): ?self
    {
        $parts = explode('_', $table, 2);
        $moduleName = $parts[0] ?? '';

        if ($moduleName === '') {
            return null;
        }

        $gen = new self($moduleName);
        if (! $gen->exists()) {
            $gen->generate();
        }

        return $gen;
    }

    /**
     * (Placeholder) Génère un contrôleur pour un modèle donné.
     *
     * @param  string  $model  Nom de classe (ex: "BlogAuthor")
     */
    public function generateController(string $model): void
    {
        // TODO: Implémenter la génération contrôleur (artisan make:controller … --module=)
    }
}
