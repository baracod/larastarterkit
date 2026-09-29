<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator;

use Baracod\Larastarterkit\Generator\Ai\Assistant;
use Baracod\Larastarterkit\Generator\Backend\Database\FactoryGen;
use Baracod\Larastarterkit\Generator\Backend\Database\MigrationGen;
use Baracod\Larastarterkit\Generator\Backend\Database\PermissionSeederGen;
use Baracod\Larastarterkit\Generator\Backend\Http\ControllerGen;
use Baracod\Larastarterkit\Generator\Backend\Http\RequestGen;
use Baracod\Larastarterkit\Generator\Backend\Http\RouteGen;
use Baracod\Larastarterkit\Generator\Backend\Model\ModelGen;
use Baracod\Larastarterkit\Generator\Backend\Model\ModelPatcher;
use Baracod\Larastarterkit\Generator\Backend\Testing\ApiTestGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Baracod\Larastarterkit\Generator\Frontend\TypeScriptGeneratorFromJson;
use Baracod\Larastarterkit\Generator\Support\ModuleAutoload;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use RuntimeException;
use Symfony\Component\Process\Process;
use Throwable;

/**
 * Orchestrateur de génération : ordonne les modes, exécute chaque générateur et enregistre l'état.
 *
 * L'état (backend.hasModel, frontend.hasApi…) est toujours relu depuis module.json avant d'être modifié :
 * les écritures des différents générateurs ne peuvent plus s'écraser mutuellement.
 */
final class GenerationOrchestrator
{
    public const GENERATED = 'generated';

    public const SKIPPED = 'skipped';

    public const FAILED = 'failed';

    private string $moduleName;

    private string $modelKey;

    private string $jsonPath;

    private bool $force = false;

    /** @var list<array{mode: GenerationMode, status: string, message: string, files: list<string>}> */
    private array $report = [];

    private ?TypeScriptGeneratorFromJson $frontend = null;

    public function __construct(string $moduleName, string $modelKey, ?string $jsonPath = null)
    {
        $this->moduleName = Str::studly($moduleName);
        $this->modelKey = Str::kebab($modelKey);
        $this->jsonPath = $jsonPath ?? base_path("Modules/{$this->moduleName}/module.json");

        if (! File::exists($this->jsonPath)) {
            throw new RuntimeException("Fichier de définition introuvable: {$this->jsonPath}");
        }
        app(\Baracod\Larastarterkit\Core\Support\ModuleRegistry::class)->assertLocal($this->moduleName);
        $this->model(); // Valide que le modèle existe.
    }

    public static function for(string $moduleName, string $modelKey, ?string $jsonPath = null): self
    {
        return new self($moduleName, $modelKey, $jsonPath);
    }

    /**
     * Régénère aussi les éléments marqués comme existants (les migrations ne sont jamais réécrites).
     *
     * @return $this
     */
    public function force(bool $force = true): self
    {
        $this->force = $force;

        return $this;
    }

    /**
     * @param  array<GenerationMode>  $modes
     * @return array<string,bool> Succès par mode (clé = valeur du mode)
     */
    public function generate(array $modes): array
    {
        $this->report = [];
        $ordered = $this->expandModes($modes);
        usort($ordered, static fn (GenerationMode $a, GenerationMode $b) => $a->order() <=> $b->order());

        if (array_filter($ordered, static fn (GenerationMode $m) => $m->group() === 'backend') !== [] && config('generator.register_autoload', true)) {
            $this->ensureAutoload();
        }

        $results = [];
        foreach ($ordered as $mode) {
            try {
                [$status, $message, $files] = $this->execute($mode) + [2 => []];
            } catch (Throwable $e) {
                [$status, $message, $files] = [self::FAILED, $e->getMessage(), []];
            }
            $this->report[] = ['mode' => $mode, 'status' => $status, 'message' => $message, 'files' => $files];
            $results[$mode->value] = $status !== self::FAILED;
        }
        $this->format();

        return $results;
    }

    /**
     * Met les fichiers PHP générés aux normes du projet (Pint), si l'outil est disponible.
     */
    private function format(): void
    {
        $files = array_values(array_filter(
            array_unique(array_merge(...array_map(static fn (array $row) => $row['files'], $this->report ?: [['files' => []]]))),
            static fn (string $file) => preg_match('/\.(php|ts|vue)$/', $file) === 1 && File::exists($file),
        ));
        $php = array_values(array_filter($files, static fn (string $f) => str_ends_with($f, '.php')));
        $pint = base_path('vendor/bin/pint');
        if ($php !== [] && config('generator.format_php', true) && File::exists($pint)) {
            $this->runQuietly([PHP_BINARY, $pint, '--quiet', ...$php]);
        }

        // Le frontend généré suit les règles ESLint du projet (espaces, ordre des attributs…).
        $frontend = array_values(array_filter($files, static fn (string $f) => preg_match('/\.(ts|vue)$/', $f) === 1));
        $eslint = base_path('node_modules/.bin/eslint');
        if ($frontend !== [] && config('generator.format_frontend', true) && File::exists($eslint)) {
            $this->runQuietly([$eslint, '-c', base_path('.eslintrc.cjs'), '--fix', '--no-ignore', ...$frontend], 300);
        }
    }

    /** @param list<string> $command */
    private function runQuietly(array $command, int $timeout = 120): void
    {
        $process = new Process($command, base_path());
        $process->setTimeout($timeout);
        $process->run();
    }

    /**
     * @param  'backend'|'frontend'|'fullstack'  $strategy
     * @return array<string,bool>
     */
    public function generateStrategy(string $strategy): array
    {
        return $this->generate(GenerationMode::forStrategy($strategy));
    }

    /**
     * Détail de la dernière génération.
     *
     * @return list<array{mode: GenerationMode, status: string, message: string, files: list<string>}>
     */
    public function report(): array
    {
        return $this->report;
    }

    /**
     * Définition du modèle, relue depuis le disque.
     */
    public function model(): DFModel
    {
        return DefinitionStore::fromFile($this->jsonPath)->module()->model($this->modelKey);
    }

    /**
     * @param  array<GenerationMode>  $modes
     * @return list<GenerationMode>
     */
    private function expandModes(array $modes): array
    {
        $expanded = [];
        foreach ($modes as $mode) {
            $parts = match ($mode) {
                GenerationMode::BACKEND_FULL => GenerationMode::backendModes(),
                GenerationMode::FRONTEND_FULL => GenerationMode::frontendModes(),
                GenerationMode::FULLSTACK => GenerationMode::expandFullstack(),
                default => [$mode],
            };
            foreach ($parts as $part) {
                $expanded[$part->value] = $part;
            }
        }

        return array_values($expanded);
    }

    /**
     * @return array{0:string,1:string,2?:list<string>}
     */
    private function execute(GenerationMode $mode): array
    {
        return match ($mode) {
            GenerationMode::BACKEND_MIGRATION => $this->genMigration(),
            GenerationMode::BACKEND_MODEL => $this->genModel(),
            GenerationMode::BACKEND_REQUEST => $this->genRequest(),
            GenerationMode::BACKEND_CONTROLLER => $this->genController(),
            GenerationMode::BACKEND_ROUTE => $this->genRoute(),
            GenerationMode::BACKEND_PERMISSIONS => $this->genPermissions(),
            GenerationMode::BACKEND_FACTORY => $this->genFactory(),
            GenerationMode::BACKEND_TEST => $this->genTest(),
            GenerationMode::FRONTEND_TYPES => $this->frontendStep('hasType', fn (TypeScriptGeneratorFromJson $gen) => [$gen->generateTypes()], 'Types TypeScript'),
            GenerationMode::FRONTEND_API => $this->frontendStep('hasApi', fn (TypeScriptGeneratorFromJson $gen) => [$gen->generateApi()], 'Client API'),
            GenerationMode::FRONTEND_INDEX => $this->frontendStep('hasIndex', fn (TypeScriptGeneratorFromJson $gen) => [$gen->generateIndexPage()], 'Page liste'),
            GenerationMode::FRONTEND_ADDOREDIT => $this->frontendStep('hasAddOrEditComponent', fn (TypeScriptGeneratorFromJson $gen) => [$gen->generateForm()], 'Formulaire'),
            GenerationMode::FRONTEND_MENU => $this->frontendStep('hasMenu', fn (TypeScriptGeneratorFromJson $gen) => [$gen->generateMenu()], 'Menu'),
            GenerationMode::FRONTEND_I18N => $this->genTranslations(),
            default => [self::FAILED, 'Mode groupé non exécutable directement.'],
        };
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Backend
    // ─────────────────────────────────────────────────────────────────────────────

    private function genMigration(): array
    {
        $result = (new MigrationGen($this->model()))->generate();
        $this->mark(fn (DFModel $m) => $m->backend()->hasMigration = true);

        return [$result['status'] === 'generated' ? self::GENERATED : self::SKIPPED, $result['message'], array_filter([$result['path']])];
    }

    private function genModel(): array
    {
        $model = $this->model();
        if ($model->backend()->hasModel && ! $this->force && File::exists((string) $model->path())) {
            return [self::SKIPPED, "Le modèle {$model->name()} existe déjà (--force pour le régénérer)."];
        }
        $gen = new ModelGen($this->modelKey, $this->moduleName);
        $gen->generate();
        $this->mark(fn (DFModel $m) => $m->backend()->hasModel = true);

        return [self::GENERATED, "Modèle {$model->name()} généré.", [$gen->getPath()]];
    }

    private function genRequest(): array
    {
        $model = $this->model();
        $gen = new RequestGen($this->modelKey, $this->moduleName);
        if ($model->backend()->hasRequest && ! $this->force && File::exists($gen->requestPath())) {
            return [self::SKIPPED, "{$model->name()}Request existe déjà (--force pour la régénérer)."];
        }
        $gen->generate();
        $this->mark(fn (DFModel $m) => $m->backend()->hasRequest = true);

        return [self::GENERATED, "{$model->name()}Request générée (règles complémentaires : {$gen->rulesSource}).", [$gen->requestPath()]];
    }

    private function genController(): array
    {
        $model = $this->model();
        $gen = ControllerGen::for($this->moduleName, $this->modelKey);
        $written = $gen->generate(force: $this->force);
        $this->mark(function (DFModel $m) {
            $m->backend()->hasController = true;
            $m->backend()->hasRequest = true;
        });

        return $written
            ? [self::GENERATED, "{$model->name()}Controller généré.", [$gen->controllerPath()]]
            : [self::SKIPPED, "{$model->name()}Controller existe déjà (--force pour le régénérer)."];
    }

    private function genRoute(): array
    {
        $model = $this->model();
        $file = base_path("Modules/{$this->moduleName}/routes/api.php");
        $resource = Str::kebab(Str::smartPlural($this->modelKey));
        $result = (new RouteGen($file))->addApiResource($resource, $model->name().'Controller', $this->moduleName, $model->tableName());
        if ($result['statut'] === 'marker_not_found') {
            return [self::FAILED, "Marqueur //{{ next-route }} absent de {$file} : ajoutez-le dans le groupe de routes du module."];
        }
        $this->mark(function (DFModel $m) use ($result) {
            $m->backend()->hasRoute = true;
            $m->backend()->apiRoute = $result['apiRoute'];
        });

        $url = '/api/v1/'.preg_replace('#^api/#', '', $result['apiRoute']);

        return [$result['statut'] === 'added' ? self::GENERATED : self::SKIPPED, "Routes « {$resource} » : {$url} ({$result['statut']}).", [$file]];
    }

    private function genPermissions(): array
    {
        $this->mark(fn (DFModel $m) => $m->backend()->hasPermission = true);
        $result = (new PermissionSeederGen($this->moduleName))->generate([$this->model()->tableName()]);

        return [self::GENERATED, "Permissions browse/add/edit/delete_{$this->model()->tableName()} : seeder mis à jour, {$result['applied']} créée(s) en base.", [$result['path']]];
    }

    private function genFactory(): array
    {
        $gen = new FactoryGen($this->model(), app(Assistant::class));
        $result = $gen->generate($this->force);
        if ($result['status'] === 'generated') {
            $this->mark(fn (DFModel $m) => $m->backend()->hasFactory = true);
            $this->linkFactoryToModel();
        }

        return [$result['status'] === 'generated' ? self::GENERATED : self::SKIPPED, $result['message']." (données : {$result['source']})", $result['paths']];
    }

    /**
     * Ajoute newFactory() au modèle existant sans le réécrire (il peut contenir du code métier).
     */
    private function linkFactoryToModel(): void
    {
        $model = $this->model();
        $path = (string) $model->path();
        if ($path === '' || ! File::exists($path)) {
            return;
        }
        $factory = "Modules\\{$this->moduleName}\\Database\\Factories\\{$model->name()}Factory";
        $code = (string) File::get($path);
        $patched = ModelPatcher::apply($code, [$factory], [], [
            "protected static function newFactory(): {$model->name()}Factory\n{\n    return {$model->name()}Factory::new();\n}",
        ]);
        if ($patched !== $code) {
            File::put($path, $patched);
        }
    }

    private function genTest(): array
    {
        $result = (new ApiTestGen($this->model()))->generate($this->force);
        if ($result['status'] === 'generated') {
            $this->mark(fn (DFModel $m) => $m->backend()->hasTest = true);
        }

        return [$result['status'] === 'generated' ? self::GENERATED : self::SKIPPED, $result['message'], array_filter([$result['path']])];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // Frontend
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * @param  callable(TypeScriptGeneratorFromJson): list<string>  $step
     */
    private function frontendStep(string $flag, callable $step, string $label): array
    {
        $files = $step($this->frontend());
        $this->mark(fn (DFModel $m) => $m->frontend()->{$flag}(true));

        return [self::GENERATED, "{$label} généré(e).", $files];
    }

    private function genTranslations(): array
    {
        $gen = $this->frontend();
        $files = $gen->generateTranslations();
        $this->mark(fn (DFModel $m) => $m->frontend()->hasLang(true));

        return [self::GENERATED, 'Traductions ('.implode(', ', array_map('basename', $files)).") : libellés par {$gen->translationSource}.", $files];
    }

    private function frontend(): TypeScriptGeneratorFromJson
    {
        // Recréé après une étape backend : l'URL de l'API peut venir d'être enregistrée.
        return $this->frontend = new TypeScriptGeneratorFromJson($this->modelKey, $this->moduleName);
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // État
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Relit module.json, applique la modification au modèle et enregistre.
     *
     * @param  callable(DFModel): mixed  $mutate
     */
    private function mark(callable $mutate): void
    {
        $store = DefinitionStore::fromFile($this->jsonPath);
        $model = $store->module()->model($this->modelKey);
        $mutate($model);
        $store->module()->upsertModel($model);
        $store->save($this->jsonPath);
    }

    private function ensureAutoload(): void
    {
        if (ModuleAutoload::register($this->moduleName) !== []) {
            ModuleAutoload::dump();
        }
    }
}
