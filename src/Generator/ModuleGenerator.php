<?php

namespace Baracod\Larastarterkit\Generator;

use Baracod\Larastarterkit\Generator\Utils\ConsoleTrait;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use Nwidart\Modules\Module as ClsModule;
use RuntimeException;

use function Laravel\Prompts\note;
use function Laravel\Prompts\select;

/**
 * Class ModuleGenerator
 *
 * Gère la génération de modules, modèles, contrôleurs et routes.
 */
class ModuleGenerator
{
    use ConsoleTrait;

    private string $moduleName;

    private string $moduleKey;

    private ?ClsModule $module = null;

    private ?string $icon = null;

    private ?string $author = null;

    private ?string $description = null;

    private ?string $groupe = null;

    private $tables = [];

    /**
     * ModuleGenerator constructor.
     *
     * @param  string  $name  Le nom du module
     *
     * @throws \Exception Si le nom est vide
     * @throws RuntimeException Si la génération du module échoue
     */
    public function __construct(string $name, ?string $icon = null, ?string $author = null, ?string $description = null, ?string $groupe = null)
    {
        $this->icon = $icon;
        $this->author = $author;
        $this->description = $description;
        $this->groupe = $groupe;
        if (empty($name)) {
            throw new \Exception('Le nom du module est requis.');
        }
        $this->moduleName = Str::pascal($name);
        $this->moduleKey = Str::lower($name);

        $dbName = Schema::getCurrentSchemaName() ?? env('DB_DATABASE');
        $listTables = collect(Schema::getTableListing($dbName));
        $listTables = $listTables->map(function ($table) use ($dbName) {
            return str_replace($dbName.'.', '', $table);
        });

        $this->tables = $listTables->filter(function ($table) {
            return Str::startsWith($table, Str::lower($this->moduleName).'_');
        })->values()->toArray();

        // Si le module n'existe pas, le générer automatiquement
        if (! Module::has($name)) {
            if (select('Le module '.$name.' n\'existe pas, voulez-vous le créer ?', ['oui', 'non']) === 'non') {
                throw new RuntimeException("Le module '{$this->moduleName}' n'existe pas.");
            }
            note("Génération {$name} du module", 'error');
            $this->generate();
            note("Module {$name} généré avec succès.", 'error');
        }

        // Affecte le module généré ou existant à la propriété $module
        $this->module = Module::find($this->moduleName);
    }

    public static function getModuleList(): array
    {
        $modules = Module::allEnabled();
        foreach ($modules as $key => $module) {
            $modules[$key] = Str::studly($key);
        }

        return $modules;
    }

    public function getTableList(): array
    {
        return $this->tables;
    }

    /**
     * Génère le module s'il n'existe pas encore.
     *
     * @return self L'instance du module généré
     *
     * @throws RuntimeException Si la commande artisan échoue
     */
    public function generate(): self
    {
        if ($this->exists()) {
            return $this;
        }

        $returnCode = Artisan::call('module:make', ['name' => $this->moduleName]);
        if ($returnCode !== 0) {
            throw new RuntimeException("La commande 'module:make {$this->moduleName}' a échoué (code {$returnCode}).");
        }
        $this->generatePermissions();

        $moduleData = [
            'icon' => $this->icon,
            'title' => $this->moduleName,
            'action' => 'access',
            'subject' => Str::lower($this->moduleName),
            'to' => [
                'name' => Str::lower($this->moduleName),
            ],
        ];

        $pathModuleMenuItems = (base_path('Modules/modules.json'));
        $moduleItems = [];
        if (File::exists($pathModuleMenuItems)) {
            $decoded = json_decode((string) File::get($pathModuleMenuItems), true);
            if (is_array($decoded)) {
                $moduleItems = array_values($decoded);
            }
        }

        $already = collect($moduleItems)->firstWhere('title', $this->moduleName);
        if (! $already) {
            $moduleItems[] = $moduleData;
        }

        File::ensureDirectoryExists(dirname($pathModuleMenuItems));
        File::put(
            $pathModuleMenuItems,
            json_encode(array_values($moduleItems), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        );

        // Met à jour la propriété $module après génération
        $this->module = Module::find($this->moduleName);

        // if (!$this->module) {
        //     throw new RuntimeException("La génération du module '{$this->moduleName}' a échoué.");
        // }

        return $this;
    }

    /**
     * générer les permissions pour le modèle.
     */
    public function generatePermissions(): void
    {
        $this->consoleWriteMessage("🔧 Génération des permissions du module `{$this->moduleName}`...");

        // Récupération du rôle administrateur
        $adminRole = DB::table('auth_roles')->where('name', 'administrator')->first();

        if (! $adminRole) {
            $this->consoleWriteError(
                "❗ Le rôle `administrator` n'existe pas.\n".
                    "Les permissions seront générées mais ne seront pas assignées automatiquement à l'administrateur."
            );
        }

        $action = 'access';
        $description = "Accéder au module {$this->moduleName}";
        $permissionKey = $action.'_'.Str::lower($this->moduleName);
        $oldPermission = DB::table('auth_permissions')->where('key', $permissionKey)->first();

        // Vérifie si la permission existe déjà
        if ($oldPermission) {
            $this->consoleWriteMessage("🔁 La permission `{$permissionKey}` existe déjà.");

            DB::table('auth_permissions')
                ->where('id', $oldPermission->id)
                ->update([
                    'description' => $description,
                    'subject' => Str::lower($this->moduleName),
                ]);

            if (isset($adminRole)) {
                $pivotPermissionRole = DB::table('auth_role_permissions')
                    ->where('permission_id', $oldPermission->id)
                    ->where('role_id', $adminRole->id)
                    ->exists();

                if (! $pivotPermissionRole) {
                    $this->consoleWriteMessage('La permission n\'est pas encore attribuée à l\'administrateur, attribution en cours...');

                    DB::table('auth_role_permissions')->insert([
                        'role_id' => $adminRole->id,
                        'permission_id' => $oldPermission->id,
                    ]);

                    $this->consoleWriteSuccess("✅ Permission `{$permissionKey}` attribuée à l'administrateur.");
                }
            }

            return;
        }

        // Création de la permission
        $permissionId = DB::table('auth_permissions')->insertGetId([
            'description' => $description,
            'table_name' => $permissionKey,
            'action' => $action,
            'subject' => Str::lower($this->moduleName),
            'key' => $permissionKey,
        ]);

        // Attribution de la permission à l'administrateur si possible
        if (isset($adminRole)) {
            DB::table('auth_role_permissions')->insert([
                'role_id' => $adminRole->id,
                'permission_id' => $permissionId,
            ]);
        }

        $this->consoleWriteSuccess("✅ Permissions générées avec succès pour `{$this->moduleName}`.");
    }

    public function delete(bool $confirmation = false): bool
    {
        if ($confirmation) {

            try {
                $pathModuleMenuItems = (base_path('Modules/modules.json'));
                $moduleItems = json_decode(File::get($pathModuleMenuItems), true);

                $moduleItems = array_filter($moduleItems, function ($item) {
                    return $item['title'] !== $this->moduleName;
                });

                $moduleItems = json_encode($moduleItems, JSON_PRETTY_PRINT);

                File::put($pathModuleMenuItems, $moduleItems);

                // Met à jour la propriété $module après génération
                $this->module = Module::find($this->moduleName);

                return Module::delete($this->moduleName);
            } catch (\Throwable $th) {
                throw $th;
            }
        }

        return false;
    }

    /**
     * Renvoie le namespace du module.
     */
    public function getNameSpace(): string
    {
        return 'Modules\\'.ucfirst($this->moduleName);
    }

    /**
     * Renvoie le chemin du module.
     *
     * @throws RuntimeException Si le module n'existe pas
     */
    public function getPath(?string $relativePath = null): string
    {
        if (! $this->module) {
            throw new RuntimeException("Le module '{$this->moduleName}' n'existe pas.");
        }

        if ($relativePath) {
            return $this->module->getPath().'/'.$relativePath;
        }

        return $this->module->getPath();
    }

    /**
     * Renvoie le namespace des contrôleurs.
     */
    public function getControllerNameSpace(): string
    {
        return $this->getNameSpace().'\\Http\\Controllers';
    }

    /**
     * Renvoie le namespace des requêtes http.
     */
    public function getRequestNamespace(): string
    {
        return $this->getNameSpace().'\\Http\\Requests';
    }

    /**
     * Renvoie le chemin vers le dossier des contrôleurs.
     */
    public function getPathControllers(): string
    {
        return $this->getPath().'/app/Http/Controllers';
    }

    /**
     * Renvoie le namespace des modèles.
     */
    public function getModelNameSpace(): string
    {
        return $this->getNameSpace().'\\Models';
    }

    /**
     * Renvoie le chemin vers le dossier des modèles.
     */
    public function getModelsDirectoryPath(): string
    {
        return $this->getPath().'/app/Models';
    }

    public function modelExist(string $modelName): bool
    {
        $modelsDirectory = $this->getModelsDirectoryPath();
        $completPath = $modelsDirectory.'/'.$modelName.'.php';

        return File::exists($completPath);
    }

    public function getModelPath(string $modelName): ?string
    {
        if ($this->modelExist($modelName)) {
            $modelsDirectory = $this->getModelsDirectoryPath();

            return $modelsDirectory.'/'.$modelName.'/.php';
        }

        return null;
    }

    public function getModels(): ?array
    {
        $modelsDirectory = $this->getModelsDirectoryPath();
        $files = File::files($modelsDirectory);
        if ($files) {
            $models = [];
            foreach ($files as $file) {
                $models[] = pathinfo($file)['filename'];
            }

            return $models;
        }

        return null;
    }

    /**
     * Renvoie le chemin vers le dossier des routes.
     */
    public function getRoutePath(): string
    {
        return $this->getPath().'/routes';
    }

    /**
     * Renvoie le chemin vers le fichier des routes API.
     */
    public function getRouteApiPath(): string
    {
        return $this->getRoutePath().'/api.php';
    }

    /**
     * Renvoie le chemin vers le fichier des routes web.
     */
    public function getRouteWebPath(): string
    {
        return $this->getRoutePath().'/web.php';
    }

    /**
     * Vérifie si le module existe.
     */
    public function exists(): bool
    {
        try {
            return Module::has($this->moduleName);
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Génère une nouvelle route dans le fichier api.php.
     *
     * @param  string  $route  La route à ajouter
     *
     * @throws RuntimeException Si la lecture ou l'écriture du fichier échoue
     */
    public function generateRoute(string $route): void
    {
        $path = $this->getRouteApiPath();
        $content = file_get_contents($path);

        if ($content === false) {
            throw new RuntimeException("Impossible de lire le fichier {$path}");
        }

        $updatedContent = str_replace('//{{ next-route }}', $route, $content);

        if (file_put_contents($path, $updatedContent) === false) {
            throw new RuntimeException("Impossible d'écrire dans le fichier {$path}");
        }
    }

    /**
     * Récupère (ou génère) un module à partir du nom d'une table.
     * On considère que le nom du module est la première partie du nom de la table, séparée par '_'.
     */
    public function getModuleOfTable(string $table): ?self
    {
        $parts = explode('_', $table);
        $moduleName = $parts[0] ?? '';

        if (empty($moduleName)) {
            return null;
        }

        $generator = new self($moduleName);

        if (! $generator->exists()) {
            $generator = $generator->generate();
        }

        return $generator;
    }

    /**
     * Génère un contrôleur pour le modèle donné.
     */
    public function generateController(string $model): void
    {
        // Implémentation de la génération du contrôleur
    }
}
