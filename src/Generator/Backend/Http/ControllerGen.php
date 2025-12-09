<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Http;

use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Baracod\Larastarterkit\Generator\ModuleGenerator;
use Baracod\Larastarterkit\Generator\Traits\StubResolverTrait;
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
    use StubResolverTrait;

    private DFModel $dfModel;

    private string $moduleName;               // Studly (ex: Blog)

    private string $modelKey;                 // kebab (ex: blog-author)

    private string $modelName;                // Studly (ex: BlogAuthor)

    private string $modelFqcn;                // ex: Modules\Blog\Models\BlogAuthor

    private ModuleGenerator $moduleGen;

    private string $controllerNamespace;      // ex: Modules\Blog\Http\Controllers

    private string $controllerDirectoryPath;  // ex: Modules/Blog/app/Http/Controllers

    private string $controllerName;           // ex: BlogAuthorController

    private string $controllerFilePath;       // ex: .../BlogAuthorController.php

    private string $controllerStubPath;       // ex: app/Generator/Backend/Stubs/backend/Controller.stub

    private string $routeApiPath;             // ex: Modules/Blog/routes/api.php

    /** Chemin du JSON de définitions: ModuleData/{kebab}.json */
    private string $jsonPath;

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
        $this->moduleGen = new ModuleGenerator($this->moduleName);
        $this->controllerNamespace = $this->moduleGen->getControllerNameSpace();
        $this->controllerDirectoryPath = $this->moduleGen->getPathControllers();
        $this->controllerName = $this->modelName.'Controller';
        $this->controllerFilePath = $this->controllerDirectoryPath.'/'.$this->controllerName.'.php';
        $this->controllerStubPath = $this->resolveStubPath('backend/Controller.stub');

        $this->routeApiPath = $this->moduleGen->getRouteApiPath();

        $this->jsonPath = $this->jsonPath($this->moduleName);
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

    /**
     * Génère le contrôleur et la FormRequest associée.
     *
     * @param  bool  $withRoute  Si true, ajoute/actualise la ressource API et met à jour le JSON.
     */
    public function generate(bool $withRoute = false): bool
    {
        if (! File::exists($this->controllerStubPath)) {
            throw new RuntimeException("Stub introuvable: {$this->controllerStubPath}");
        }

        // 1) S'assure que la FormRequest existe (met à jour backend.hasRequest)
        (new RequestGen($this->modelKey, $this->moduleName))->generate();

        // 2) Construire le contrôleur depuis le stub
        $requestName = $this->modelName.'Request';
        $requestFqcn = $this->moduleGen->getRequestNamespace().'\\'.$requestName;

        $replacements = [
            '{{ controllerNamespace }}' => $this->controllerNamespace,
            '{{ controllerName }}' => $this->controllerName,
            '{{ modelFqcn }}' => $this->modelFqcn,
            '{{ modelName }}' => $this->modelName,
            '{{ requestFqcn }}' => $requestFqcn,
            '{{ requestName }}' => $requestName,
            '{{ requestNamespace }}' => '', // si inutilisé dans le stub
        ];

        $template = File::get($this->controllerStubPath);
        $content = strtr($template, $replacements);

        File::ensureDirectoryExists($this->controllerDirectoryPath, 0755);

        // Écrire si absent ; si présent on ne réécrit pas
        if (! File::exists($this->controllerFilePath)) {
            File::put($this->controllerFilePath, $content);
            $this->markHasController(); // maj store JSON
        }

        // 3) Optionnel : ressource API + flags JSON
        if ($withRoute) {
            $this->updateRoute();
        }

        return true;
    }

    /**
     * Ajoute/actualise la ressource API dans routes/api.php du module et met à jour le JSON.
     */
    public function updateRoute(): bool
    {
        if (! File::exists($this->routeApiPath)) {
            // Certains modules n'ont pas d'API exposée : on ne jette pas d'exception.
            return false;
        }

        $routeGen = new RouteGen($this->routeApiPath);

        // Nom de ressource: plural-kebab à partir de la key (ex: blog-author → blog-authors)
        $resource = method_exists(Str::class, 'smartPlural')
            ? Str::kebab(Str::smartPlural($this->modelKey))
            : Str::kebab(Str::plural($this->modelKey));

        $res = $routeGen->addApiResource($resource, $this->controllerName, $this->moduleName);
        $apiRoute = $res['apiRoute'] ?? null;

        $this->markHasRoute($apiRoute);

        return true;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Persistance (DefinitionStore)
    // ─────────────────────────────────────────────────────────────────────────────

    private function markHasController(): void
    {
        if (! File::exists($this->jsonPath)) {
            return;
        }
        $store = DefinitionStore::fromFile($this->jsonPath);
        $df = $store->module()->model($this->modelKey);

        $df->backend()->hasController = true;

        $store->module()->upsertModel($df);
        $store->save($this->jsonPath);
    }

    private function markHasRoute(?string $apiRoute): void
    {
        if (! File::exists($this->jsonPath)) {
            return;
        }
        $store = DefinitionStore::fromFile($this->jsonPath);
        $df = $store->module()->model($this->modelKey);

        $df->backend()->hasRoute = true;
        if ($apiRoute) {
            $df->backend()->apiRoute = $apiRoute;
        }

        $store->module()->upsertModel($df);
        $store->save($this->jsonPath);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Utils
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Chemin du JSON de définitions du module (nouveau système).
     */
    private static function jsonPath(string $moduleName, bool $ensureDir = false): string
    {
        $path = Module::getModulePath($moduleName).'module.json';

        return $path;
    }
}
