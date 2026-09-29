<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Http;

use Baracod\Larastarterkit\Generator\Backend\ModuleGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;
use RuntimeException;

/**
 * Génère le contrôleur REST d’un modèle et (optionnellement) sa ressource API.
 *
 * Hydratation à partir d’un DFModel (définition typée).
 */
final class ControllerGen
{
    private DFModel $dfModel;

    private string $moduleName;               // Studly (ex: Blog)

    private string $modelKey;                 // kebab (ex: blog-author)

    private string $modelName;                // Studly (ex: BlogAuthor)

    private string $modelFqcn;                // ex: Modules\Blog\Models\BlogAuthor

    private ModuleGen $moduleGen;

    private string $controllerNamespace;      // ex: Modules\Blog\Http\Controllers

    private string $controllerDirectoryPath;  // ex: Modules/Blog/app/Http/Controllers

    private string $controllerName;           // ex: BlogAuthorController

    private string $controllerFilePath;       // ex: .../BlogAuthorController.php

    private string $controllerStubPath;       // ex: app/Generator/Backend/Stubs/backend/Controller.stub

    private string $routeApiPath;             // ex: Modules/Blog/routes/api.php

    /**
     * @param  DFModel  $dfModel  Définition typée du modèle.
     */
    public function __construct(DFModel $dfModel)
    {
        $this->dfModel = $dfModel;

        $this->moduleName = Str::studly($dfModel->moduleName());
        $this->modelKey = $dfModel->key();
        $this->modelName = $dfModel->name();
        $this->modelFqcn = (string) ($dfModel->fqcn() ?: rtrim($dfModel->namespace(), '\\').'\\'.$this->modelName);

        // Générateur de module (chemins/namespaces)
        $this->moduleGen = new ModuleGen($this->moduleName);
        $this->controllerNamespace = $this->moduleGen->getControllerNameSpace();
        $this->controllerDirectoryPath = $this->moduleGen->getPathControllers();
        $this->controllerName = $this->modelName.'Controller';
        $this->controllerFilePath = $this->controllerDirectoryPath.'/'.$this->controllerName.'.php';
        $this->controllerStubPath = dirname(__DIR__, 2).'/Backend/Stubs/backend/Controller.stub';
        $this->routeApiPath = $this->moduleGen->getRouteApiPath();
    }

    /**
     * Fabrique un ControllerGen à partir de {module, modelKey}.
     */
    public static function for(string $moduleName, string $modelKey): self
    {
        $jsonPath = self::jsonPath($moduleName);
        if (! File::exists($jsonPath)) {
            throw new RuntimeException("Fichier de définition introuvable: {$jsonPath}");
        }
        $store = DefinitionStore::fromFile($jsonPath);
        $dfModel = $store->module()->model($modelKey);

        return new self($dfModel);
    }

    public function controllerPath(): string
    {
        return $this->controllerFilePath;
    }

    /**
     * Génère le contrôleur (et la FormRequest si elle manque).
     * Un contrôleur existant n'est réécrit qu'avec $force : il peut contenir du code métier.
     *
     * @return bool true si le fichier a été écrit
     */
    public function generate(bool $withRoute = false, bool $force = false): bool
    {
        if (! File::exists($this->controllerStubPath)) {
            throw new RuntimeException("Stub introuvable: {$this->controllerStubPath}");
        }

        $requestGen = new RequestGen($this->modelKey, $this->moduleName);
        if (! File::exists($requestGen->requestPath())) {
            $requestGen->generate();
        }

        if (File::exists($this->controllerFilePath) && ! $force) {
            return false;
        }

        $relations = $this->extractBelongsToRelations();
        $relationList = $relations ? "['".implode("', '", $relations)."']" : '';
        $columns = $this->columns();

        $content = strtr(File::get($this->controllerStubPath), [
            '{{ controllerNamespace }}' => $this->controllerNamespace,
            '{{ controllerName }}' => $this->controllerName,
            '{{ modelFqcn }}' => $this->modelFqcn,
            '{{ modelName }}' => $this->modelName,
            '{{ requestFqcn }}' => $this->moduleGen->getRequestNamespace().'\\'.$this->modelName.'Request',
            '{{ requestName }}' => $this->modelName.'Request',
            '{{ eagerLoading }}' => $relationList ? "->with({$relationList})" : '',
            '{{ loadRelations }}' => $relationList ? "->load({$relationList})" : '',
            '{{ searchable }}' => $this->quoteList($columns['searchable']),
            '{{ sortable }}' => $this->quoteList($columns['sortable']),
        ]);

        File::ensureDirectoryExists($this->controllerDirectoryPath, 0755);
        File::put($this->controllerFilePath, $content);

        if ($withRoute) {
            $this->updateRoute();
        }

        return true;
    }

    /**
     * @return array{searchable:list<string>, sortable:list<string>}
     */
    private function columns(): array
    {
        $searchable = [];
        $sortable = ['id'];
        foreach ($this->dfModel->fields() as $field) {
            if (in_array($field->type, [FieldType::String, FieldType::Text, FieldType::Enum, FieldType::Uuid], true)) {
                $searchable[] = $field->name;
            }
            if ($field->type !== FieldType::Json && $field->type !== FieldType::Text) {
                $sortable[] = $field->name;
            }
        }
        if ($this->dfModel->usesTimestamps()) {
            array_push($sortable, 'created_at', 'updated_at');
        }

        return ['searchable' => $searchable, 'sortable' => array_values(array_unique($sortable))];
    }

    /** @param list<string> $values */
    private function quoteList(array $values): string
    {
        return implode(', ', array_map(static fn (string $v) => var_export($v, true), $values));
    }

    /**
     * Ajoute/actualise la ressource API dans routes/api.php du module.
     *
     * @return array{statut:string, apiRoute:?string}
     */
    public function updateRoute(): array
    {
        if (! File::exists($this->routeApiPath)) {
            return ['statut' => 'no_route_file', 'apiRoute' => null];
        }

        $resource = Str::kebab(Str::smartPlural($this->modelKey));

        return (new RouteGen($this->routeApiPath))->addApiResource($resource, $this->controllerName, $this->moduleName, $this->dfModel->tableName() ?: null);
    }

    /**
     * Extrait les noms des relations belongsTo depuis le DFModel.
     *
     * @return array<string> Liste des noms de relations à eager loader
     */
    private function extractBelongsToRelations(): array
    {
        $relations = $this->dfModel->relations() ?? [];
        $belongsToRelations = [];

        foreach ($relations as $rel) {
            if (($rel['type'] ?? '') === 'belongsTo') {
                // Utiliser le nom de la relation (camelCase par défaut)
                $relationName = $rel['name'] ?? Str::camel(Str::singular($rel['table'] ?? ''));
                if ($relationName) {
                    $belongsToRelations[] = $relationName;
                }
            }
        }

        return $belongsToRelations;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Utils
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Chemin du JSON de définitions du module (nouveau système).
     */
    private static function jsonPath(string $moduleName): string
    {
        return Module::getModulePath($moduleName).'module.json';
    }
}
