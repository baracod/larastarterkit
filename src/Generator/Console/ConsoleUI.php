<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Console;

use Baracod\Larastarterkit\Generator\Backend\Http\ApiDocGen;
use Baracod\Larastarterkit\Generator\Backend\Http\ControllerGen;
use Baracod\Larastarterkit\Generator\Backend\Http\RouteGen;
use Baracod\Larastarterkit\Generator\Backend\Model\ModelGen;
use Baracod\Larastarterkit\Generator\DefinitionFile\DefinitionStore;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition as DField;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition as DFModel;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModuleDefinition as DFModule;
use Baracod\Larastarterkit\Generator\Frontend\TypeScriptGeneratorFromJson;
use Baracod\Larastarterkit\Generator\ModuleGenerator;
use Baracod\Larastarterkit\Generator\Traits\SqlConversion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Gère un fichier JSON {module, models:{...}} via l'ORM DefinitionFile (typé),
 * et fournit une UI pour créer/éditer les définitions de modèles (fillable, relations, meta).
 */
final class ConsoleUI
{
    use SqlConversion;

    private const UI_SCROLL = 20;

    private const TECH_COLS = ['id', 'created_at', 'updated_at', 'deleted_at', 'uuid'];

    private const MENU_BACK = '« Retour';

    private const MENU_SAVE = '💾 Enregistrer';

    private const MENU_DELETE = '🗑️ Supprimer';

    private const MENU_ADD = '➕ Ajouter';

    private const MENU_EDIT = '✏️ Éditer';

    private const MENU_NEXT = '#Suivant';

    private string $moduleName;

    private ModuleGenerator $moduleGen;

    private string $jsonPath;

    private DefinitionStore $store;

    private DFModule $moduleDef;

    public function __construct(?string $moduleName, ?string $jsonPath = null)
    {
        $this->moduleName = Str::studly($moduleName);
        $this->moduleGen = new ModuleGenerator($this->moduleName);


        // Fichier de définition (ex: Modules/Blog/module.json)
        $this->jsonPath = $jsonPath;
        $this->ensureFile();
        $this->store = DefinitionStore::fromFile($this->jsonPath);
        $this->moduleDef = $this->store->module();
    }

    /**
     * Ouvre un manager pour un module fourni, ou lance un sélecteur/creator si null.
     *
     * @param  string|null  $moduleName  Nom du module (Studly). Si null ⇒ sélection/creation interactive.
     * @param  string|null  $jsonDir  Répertoire des JSON (défaut: base_path('ModuleData'))
     */
    public static function for(?string $moduleName = null, ?string $jsonDir = null): self
    {
        if ($moduleName === null || $moduleName === null) {
            return self::pickOrCreate(jsonDir: $jsonDir);
        }

        return new self($moduleName, $jsonDir);
    }

    /**
     * Sélectionne un module existant ou en crée un nouveau, puis retourne le manager.
     */
    public static function pickOrCreate(?string $jsonDir = null): self
    {
        while (true) {
            $mods = ModuleGenerator::getModuleList(); // ex: ['Blog','Commerce', ...]
            $options = [];

            if (! empty($mods)) {
                foreach ($mods as $m) {
                    $options[$m] = "📦 {$m}";
                }
            }
            $options['__create'] = '➕ Créer un module';
            $options['__refresh'] = '🔄 Rafraîchir la liste';
            $options['__cancel'] = '❌ Annuler';

            $choice = select(
                label: 'Sélectionner un module',
                options: $options,
                default: ! empty($mods) ? array_key_first($options) : '__create',
                scroll: 20
            );

            if ($choice === '__cancel') {
                throw new \RuntimeException('Action annulée par l’utilisateur.');
            }

            if ($choice === '__refresh') {
                // boucle -> rechargera la liste
                continue;
            }

            if ($choice === '__create') {
                $name = trim(text('Nom du module (Studly autorisés)', 'Blog'));
                if ($name === '') {
                    warning('Nom vide. Réessaie.');

                    continue;
                }

                $studly = Str::studly($name);
                $ModuleGen = new ModuleGenerator($studly);
                $ModuleGen->generate();

                info("Module « {$studly} » prêt.");
                $jsonDir = Module::getModulePath($studly).'module.json';

                return new self($studly, $jsonDir);
            }

            if ($jsonDir === null) {
                $jsonDir = Module::getModulePath($choice).'module.json';
            }

            // choix d’un module existant
            return new self($choice, $jsonDir);
        }
    }

    /**
     * Crée la structure initiale si absent.
     */
    private function ensureFile(): void
    {
        if (! File::exists($this->jsonPath)) {
            $data = [
                'module' => $this->moduleName,
                'models' => new \stdClass,
            ];
            File::put(
                $this->jsonPath,
                json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
            );
        }
    }

    public function getJsonPath(): string
    {
        return $this->jsonPath;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // UI PRINCIPALE
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Raccourci: lance le flux interactif complet (sélection/création ⇒ UI des modèles).
     *
     * @return array{module:string,models:array<string,array<string,mixed>>}
     */
    public static function interactiveStart(?string $moduleName = null, ?string $jsonDir = null): array
    {
        $mgr = self::for($moduleName, $jsonDir);

        return $mgr->interactive();
    }

    /**
     * Menu principal : créer/éditer/supprimer des modèles (via ORM).
     *
     * @return array{module:string,models:array<string,array<string,mixed>>} snapshot JSON final
     */
    public function interactive()
    {
        // Génère/maj la doc API d’entrée (si tu veux, sinon retire)
        $apiDocGen = new ApiDocGen('./swagger.json');
        $apiDocGen->build($this->store->module()->toArray());

        while (true) {
            $models = $this->moduleDef->all(); // array<string, DFModel>
            $options = [];

            foreach ($models as $key => $m) {
                $options[$key] = $m->name().'  ·  '.$m->tableName();
            }
            $options['__create'] = '➕ Créer un modèle';

            $choice = select(
                label: "Module « {$this->moduleName} » — Modèles",
                options: $options,
                default: array_key_first($options),
                scroll: self::UI_SCROLL
            );

            if ($choice === '__create') {
                $model = $this->createModelUI(); // DFModel|null
                if ($model) {
                    // upsert dans le module
                    $this->moduleDef->upsertModel($model);
                    $this->store->save($this->jsonPath);

                    info("La structure du modèle « {$model->name()} » est ajoutée.");
                    // génération de fichiers PHP si besoin
                    $this->genPhpClass($model);
                }

                continue;
            }

            // Édition d’un modèle existant
            /** @var DFModel $model */
            $model = $this->moduleDef->model($choice);

            $edited = $this->editModelUI($model); // DFModel|null
            if ($edited === null) {
                // Suppression
                $this->moduleDef->deleteModel($choice);
                info("Modèle « {$choice} » supprimé.");
            } else {
                // upsert
                $this->moduleDef->upsertModel($edited);
                // si renommage de key → handled car key() reste identique normalement
            }

            $this->store->save($this->jsonPath);

            // Générations backend/frontend suite à édition
            if ($edited) {
                $this->genPhpClass($edited);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CRÉATION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * UI de création : table → nom → fillable → relations → meta → DFModel.
     */
    private function createModelUI(): ?DFModel
    {
        $tables = $this->moduleGen->getTableList();
        if ($tables === []) {
            warning('Aucune table trouvée pour ce module.');

            return null;
        }

        $table = select(
            label: 'Sélectionner la table',
            options: $this->toOptions($tables, false),
            default: array_key_first($tables),
            scroll: self::UI_SCROLL
        );

        $suggestedName = Str::studly(Str::singular($table));
        $name = trim(text('Nom du modèle', "Par défaut : {$suggestedName}"));
        $name = $name !== '' ? $name : $suggestedName;
        $namespace = $this->moduleGen->getModelNameSpace();
        $path = rtrim($this->moduleGen->getModelsDirectoryPath(), '/').'/'.$name.'.php';
        $fqcn = $namespace.'\\'.$name;
        $key = Str::kebab($name);



        // Fillable via types mappés → FieldDefinition[]
        $fillableFields = $this->buildFillableFromTable($table); // list<DField>

        // Relations belongsTo (optionnelles) — tableau libre, on l’injecte via fromArray()
        $relations = $this->collectBelongsToRelationsUI(
            array_map(
                fn (DField $f) => ['name' => $f->name, 'type' => $f->type->value, 'defaultValue' => $f->defaultValue, 'customizedType' => $f->customizedType],
                $fillableFields
            )
        );

        // Métadonnées backend/frontend initiales
        $backend = [
            'hasModel' => false,
            'hasController' => false,
            'hasRequest' => false,
            'hasRoute' => false,
            'hasPermission' => false,
        ];
        $frontend = [
            'hasType' => false,
            'hasApi' => false,
            'hasLang' => false,
            'hasAddOrEditComponent' => false,
            'hasReadComponent' => false,
            'hasIndex' => false,
            'hasMenu' => false,
            'hasPermission' => false,
            'fields' => [],
            'casl' => [
                'create' => false,
                'read' => false,
                'update' => false,
                'delete' => false,
                'access' => false,
            ],
        ];
        echo("test 1\n");

        $arr = [
            'name' => $name,
            'key' => $key,
            'namespace' => $namespace,
            'tableName' => $table,
            'moduleName' => $this->moduleName,
            'fillable' => array_map(fn (DField $f) => $f->toArray(), $fillableFields),
            'relations' => $relations,
            'path' => $path,
            'fqcn' => $fqcn,
            'backend' => $backend,
            'frontend' => $frontend,
        ];

        $model = DFModel::fromArray($arr);

        // Réglages meta immédiats ?
        // if (confirm('Configurer maintenant les indicateurs backend/frontend ?', default: false)) {
        //     $model = $this->configureMetaUI($model);
        // }

        // Persiste
        $this->moduleDef->createModel($model);
        $this->store->save($this->jsonPath);
        info("Modèle « {$name} » prêt.");

        return $model;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ÉDITION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * UI d’édition d’un modèle typé.
     *
     * @return DFModel|null null => supprimé
     */
    private function editModelUI(DFModel $model): ?DFModel
    {
        while (true) {
            $backend = $model->backend();
            $frontend = method_exists($model, 'frontend') ? $model->frontend() : (object) [];

            $summary = [
                ['Nom', $model->name()],
                ['Key', $model->key()],
                ['Table', $model->tableName()],
                ['Namespace', $model->namespace()],
                ['FQCN', (string) $model->fqcn()],
                ['Fillable', (string) count($model->fields())],
                ['Relations', (string) count($model->relations())],
                ['----------', '-----------------'],
                ['Model (backend)',      $backend->hasModel ? 'oui' : 'non'],
                ['Controller (backend)', $backend->hasController ? 'oui' : 'non'],
                ['Request (backend)',    $backend->hasRequest ? 'oui' : 'non'],
                ['Route (backend)',      $backend->hasRoute ? 'oui' : 'non'],
                ['----------', '-----------------'],
                // Ces champs frontend sont optionnels si ton DFModel ne les expose pas encore.
                ['Types TS (frontend)',      ($frontend->hasTypes ?? false) ? 'oui' : 'non'],
                ['Client API (frontend)',    ($frontend->hasClient ?? false) ? 'oui' : 'non'],
                ['Pages Vue (frontend)',     ($frontend->hasPages ?? false) ? 'oui' : 'non'],
                ['Menu intégré (frontend)',  ($frontend->inMenu ?? false) ? 'oui' : 'non'],
            ];
            table(headers: ['Champ', 'Valeur'], rows: $summary);

            // Backend
            $A_CREATE_MODEL = '__create_model';
            $A_CREATE_CONTROLLER = '__create_controller';
            $A_CREATE_REQUEST = '__create_request';
            $A_UPDATE_ROUTE = '__update_route';
            $A_EDIT_FILLABLE = '__edit_fillable';
            $A_EDIT_RELATIONS = '__edit_relations';
            $A_META = '__configure_meta';
            $A_DELETE = '__delete_model';

            // Frontend (nouveau)
            $A_FRONT_ALL = '__front_all';         // tout-en-un
            $A_FRONT_TYPES = '__front_types';       // générer/mettre à jour types TS (.d.ts + classes .ts)
            $A_FRONT_CLIENT = '__front_client';      // générer client ofetch pour l’entité
            $A_FRONT_PAGES = '__front_pages';       // générer pages Vue (index + AddOrEdit)
            $A_FRONT_MENU = '__front_menu';        // injecter/mettre à jour menuItems
            $A_FRONT_ROUTES = '__front_routes';      // (optionnel) déclarations de routes front si tu en as

            $actions = [];

            // Propositions backend
            if (! $backend->hasModel) {
                $actions[$A_CREATE_MODEL] = 'Créer un modèle (backend)';
            }
            if (! $backend->hasController) {
                $actions[$A_CREATE_CONTROLLER] = 'Créer un contrôleur (backend)';
            }
            if (! $backend->hasRequest) {
                $actions[$A_CREATE_REQUEST] = 'Créer une FormRequest (backend)';
            }
            if (! $backend->hasRoute) {
                $actions[$A_UPDATE_ROUTE] = 'Mettre à jour la route API (backend)';
            }

            $actions[$A_EDIT_FILLABLE] = 'Éditer les fillable';
            $actions[$A_EDIT_RELATIONS] = 'Éditer les relations';
            $actions[$A_META] = 'Configurer backend/frontend';

            // Propositions frontend (toujours visibles—idempotentes)
            $actions[$A_FRONT_ALL] = 'Créer / régénérer le frontend REST (tout-en-un)';
            // $actions[$A_FRONT_TYPES]  = 'Générer / mettre à jour les types TypeScript';
            // $actions[$A_FRONT_CLIENT] = 'Générer / mettre à jour le client API (ofetch)';
            // $actions[$A_FRONT_PAGES]  = 'Générer / mettre à jour les pages Vue (index + AddOrEdit)';
            // $actions[$A_FRONT_MENU]   = 'Mettre à jour le menu (menuItems.ts)';
            // $actions[$A_FRONT_ROUTES] = 'Mettre à jour les routes front (si applicable)';

            // Divers
            $actions[$A_DELETE] = 'Supprimer le modèle';
            $actions[self::MENU_BACK] = self::MENU_BACK;

            $choice = select('Action', $actions, default: self::MENU_BACK, scroll: self::UI_SCROLL);

            if ($choice === self::MENU_BACK) {
                return $model;
            }

            if ($choice === $A_DELETE) {
                if (confirm("Supprimer « {$model->key()} » ?", false)) {
                    return null;
                }

                continue;
            }

            // Backend
            if ($choice === $A_CREATE_MODEL) {
                $this->generateModel($model);

                continue;
            }
            if ($choice === $A_CREATE_CONTROLLER) {
                $this->generateController($model);

                continue;
            }
            if ($choice === $A_CREATE_REQUEST) {
                info('Générateur de Request non encore implémenté.');

                continue;
            }
            if ($choice === $A_UPDATE_ROUTE) {
                $this->updateRoute($model);

                continue;
            }
            if ($choice === $A_EDIT_FILLABLE) {
                $model = $this->editFillableUI($model);
                $this->moduleDef->upsertModel($model);
                $this->store->save($this->jsonPath);

                continue;
            }
            if ($choice === $A_EDIT_RELATIONS) {
                $model = $this->editRelationsUI($model);
                $this->moduleDef->upsertModel($model);
                $this->store->save($this->jsonPath);

                continue;
            }
            if ($choice === $A_META) {
                $model = $this->configureMetaUI($model);
                $this->moduleDef->upsertModel($model);
                $this->store->save($this->jsonPath);

                continue;
            }

            // Frontend — nouveau
            if ($choice === $A_FRONT_ALL) {
                // Orchestrateur : types -> client -> pages -> menu -> routes
                $this->generateFrontend($model);
                // $this->generateFrontApiClient($model);
                // $this->generateFrontPages($model);      // index.vue + components/[Model]/AddOrEdit.vue
                // $this->updateFrontMenu($model);         // resources/ts/menuItems.ts
                // $this->updateFrontRoutes($model);       // si tu gères des routes auto
                $this->markFrontendFlags($model);
                $this->moduleDef->upsertModel($model);
                $this->store->save($this->jsonPath);

                continue;
            }
        }
    }

    /**
     * Met à jour les drapeaux du frontend dans la définition du modèle.
     *
     * Exemple :
     * $this->markFrontendFlags(DFModel $dFModel,);
     */
    private function markFrontendFlags(DFModel $dFModel): void
    {
        $modelData = $this->moduleDef->model(key: $dFModel->key());

        if (! $modelData) {
            warning("⚠️ Impossible de trouver le modèle '{$modelData->key()}' dans la définition du module.");

            return;
        }

        $frontend = $dFModel->frontend();
        $modelData->frontend(
            $frontend
                ->hasApi(true)
                ->hasApi(true)
                ->hasType(true)
                ->hasMenu(true)
        );

        $this->moduleDef->upsertModel(model: $modelData);

        $this->moduleDef->save();
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SOUS-MENUS (typed)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Édite la liste des fillable d’un modèle (via table introspection).
     */
    private function editFillableUI(DFModel $model): DFModel
    {
        $table = $model->tableName();

        // Reconstruction full depuis la table (avec conservation des customizedType existants)
        $current = $model->fields(); // array<string, DField>
        $currentMap = [];
        foreach ($current as $f) {
            $currentMap[$f->name] = $f->customizedType ?? '';
        }

        $fresh = $this->buildFillableFromTable($table); // list<DField>
        foreach ($fresh as $f) {
            if (($currentMap[$f->name] ?? '') !== '') {
                $f->customizedType = $currentMap[$f->name];
            }
        }

        // Option : personnaliser
        if (confirm('Changer le type de colonnes ?', false)) {
            $fresh = $this->customizeTypesUI($table, $fresh);
        }

        // Remplace le modèle via fromArray (car pas de setFields dans l’ORM)
        $arr = $model->toArray();
        $arr['fillable'] = array_map(fn (DField $f) => $f->toArray(), $fresh);

        return DFModel::fromArray($arr);
    }

    /**
     * Édite les relations (tableau libre) sur un modèle typé,
     * puis rematérialise l’objet via fromArray.
     */
    private function editRelationsUI(DFModel $model): DFModel
    {
        $rels = $model->relations() ?: [];

        while (true) {
            table(
                headers: ['#', 'type', 'name', 'foreignKey', 'table', 'module'],
                rows: array_map(
                    fn ($r, $i) => [$i, $r['type'] ?? '', $r['name'] ?? '', $r['foreignKey'] ?? '', $r['table'] ?? '', $r['moduleName'] ?? ''],
                    $rels,
                    array_keys($rels)
                )
            );

            $choice = select('Relations', [
                '__add' => self::MENU_ADD,
                '__edit' => self::MENU_EDIT,
                '__del' => self::MENU_DELETE,
                self::MENU_BACK => self::MENU_BACK,
            ]);

            if ($choice === self::MENU_BACK) {
                $arr = $model->toArray();
                $arr['relations'] = array_values($rels);

                return DFModel::fromArray($arr);
            }

            if ($choice === '__add') {
                $freshForUi = array_map(
                    fn (DField $f) => ['name' => $f->name, 'type' => $f->type->value, 'defaultValue' => $f->defaultValue, 'customizedType' => $f->customizedType],
                    array_values($model->fields())
                );
                $new = $this->collectBelongsToRelationsUI($freshForUi);
                $rels = array_values(array_merge($rels, $new));

                continue;
            }

            if ($choice === '__edit') {
                if ($rels === []) {
                    warning('Aucune relation.');

                    continue;
                }
                $idx = (int) text('Index de la relation à éditer');
                if (! isset($rels[$idx])) {
                    warning('Index invalide.');

                    continue;
                }
                $rels[$idx] = $this->editSingleRelationUI($rels[$idx]);

                continue;
            }

            if ($choice === '__del') {
                if ($rels === []) {
                    warning('Aucune relation.');

                    continue;
                }
                $idx = (int) text('Index de la relation à supprimer');
                if (isset($rels[$idx]) && confirm('Confirmer suppression ?', false)) {
                    unset($rels[$idx]);
                    $rels = array_values($rels);
                }

                continue;
            }
        }
    }

    /**
     * Configuration backend/frontend (flags) sur un modèle typé.
     */
    private function configureMetaUI(DFModel $model): DFModel
    {
        // Backend flags
        $b = $model->backend();
        $b->hasController = confirm('Backend · hasController ?', (bool) $b->hasController);
        $b->hasRequest = confirm('Backend · hasRequest ?', (bool) $b->hasRequest);
        $b->hasRoute = confirm('Backend · hasRoute ?', (bool) $b->hasRoute);
        $b->hasPermission = confirm('Backend · hasPermission ?', (bool) $b->hasPermission);

        // Frontend flags
        $f = $model->frontend();
        $f->hasType = confirm('Frontend · hasType ?', (bool) $f->hasType);
        $f->hasApi = confirm('Frontend · hasApi ?', (bool) $f->hasApi);
        $f->hasLang = confirm('Frontend · hasLang ?', (bool) $f->hasLang);
        $f->hasAddOrEditComponent = confirm('Frontend · hasAddOrEditComponent ?', (bool) $f->hasAddOrEditComponent);
        $f->hasReadComponent = confirm('Frontend · hasReadComponent ?', (bool) $f->hasReadComponent);
        $f->hasIndex = confirm('Frontend · hasIndex ?', (bool) $f->hasIndex);
        $f->hasMenu = confirm('Frontend · hasMenu ?', (bool) $f->hasMenu);
        $f->hasPermission = confirm('Frontend · hasPermission ?', (bool) $f->hasPermission);

        // CASL
        $f->casl->create = confirm('CASL · create ?', (bool) $f->casl->create);
        $f->casl->read = confirm('CASL · read ?', (bool) $f->casl->read);
        $f->casl->update = confirm('CASL · update ?', (bool) $f->casl->update);
        $f->casl->delete = confirm('CASL · delete ?', (bool) $f->casl->delete);
        $f->casl->access = confirm('CASL · access ?', (bool) $f->casl->access);

        return $model;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  CREATION DE CLASSES PHP (depuis DFModel)
    // ─────────────────────────────────────────────────────────────────────────────

    private function genPhpClass(DFModel $model): void
    {
        $A_NEXT = self::MENU_NEXT;
        $A_CREATE_MODEL = '__create_model';
        $A_CREATE_CONTROLLER = '__create_controller';
        $A_CREATE_REQUEST = '__create_request';
        $A_UPDATE_ROUTE = '__update_route';
        $A_API_REST = '__api_rest';
        $A_FRONTEND = '__frontend';

        $name = $model->name();
        $options = [];

        if (! $model->backend()->hasModel) {
            $options[$A_CREATE_MODEL] = "Générer le modèle : {$name}.php";
        }
        if (! $model->backend()->hasController) {
            $options[$A_CREATE_CONTROLLER] = "Générer le contrôleur : {$name}Controller.php";
        }
        if (! $model->backend()->hasRoute) {
            $options[$A_UPDATE_ROUTE] = "Ajouter la route pour {$name}Controller.php";
        }

        $options[$A_API_REST] = 'Générer API REST (modèle + contrôleur + route)';
        $options[$A_FRONTEND] = "Générer le frontend REST : {$name}";
        $options[$A_NEXT] = 'Continuer';

        while (true) {
            $action = select('Que veux-tu générer ?', $options);
            if (! array_key_exists($action, $options)) {
                $action = array_search($action, $options, true) ?: $A_NEXT;
            }

            switch ($action) {
                case $A_CREATE_MODEL:
                    $this->generateModel($model);
                    unset($options[$A_CREATE_MODEL]);
                    break;

                case $A_CREATE_CONTROLLER:
                    $this->generateController($model);
                    unset($options[$A_CREATE_CONTROLLER]);
                    break;

                case $A_UPDATE_ROUTE:
                    $this->updateRoute($model);
                    break;

                case $A_API_REST:
                    $this->generateApiRest($model);
                    unset($options[$A_CREATE_MODEL], $options[$A_CREATE_CONTROLLER]);
                    break;

                case $A_FRONTEND:
                    $this->generateFrontend($model);
                    break;

                case $A_NEXT:
                default:
                    return;
            }
        }
    }

    private function generateModel(DFModel $model): void
    {
        if ($model->backend()->hasModel) {
            info("Le modèle {$model->name()} existe déjà.");

            return;
        }

        $gen = new ModelGen($model->key(), $model->moduleName());
        if ($gen->generate()) {
            info("Modèle {$model->name()} créé.");
            $model->backend()->hasModel = true;
            $this->moduleDef->upsertModel($model);
            $this->store->save($this->jsonPath);
        }
    }

    private function generateController(DFModel $model): void
    {
        if ($model->backend()->hasController) {
            info("Le contrôleur {$model->name()}Controller existe déjà.");

            return;
        }

        $controllerGen = ControllerGen::for($model->moduleName(), $model->key());
        if ($controllerGen->generate()) {
            info("Contrôleur {$model->name()}Controller créé.");
            $model->backend()->hasController = true;
            // la génération de controller crée aussi une Request basique
            $model->backend()->hasRequest = true;
            $this->moduleDef->upsertModel($model);
            $this->store->save($this->jsonPath);
        }
    }

    private function updateRoute(DFModel $model): void
    {
        $routeGen = new RouteGen($this->moduleGen->getRouteApiPath());

        $routeName = method_exists(Str::class, 'smartPlural')
            ? Str::kebab(Str::smartPlural($model->key()))
            : Str::kebab(Str::plural($model->key()));

        $res = $routeGen->addApiResource($routeName, "{$model->name()}Controller", $this->moduleName);

        $model->backend()->hasRoute = true;
        $model->backend()->apiRoute = $res['apiRoute'] ?? null;

        $this->moduleDef->upsertModel($model);
        $this->store->save($this->jsonPath);

        info("Route API resource « {$routeName} » mise à jour.");
    }

    private function generateApiRest(DFModel $model): void
    {
        $this->generateModel($model);
        $this->generateController($model);
        $this->updateRoute($model);
    }

    private function generateFrontend(DFModel $model): void
    {
        $tsGen = new TypeScriptGeneratorFromJson($model->key(), $model->moduleName());
        if ($tsGen->generate()) {
            info("Frontend pour {$model->name()} généré.");
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // BLOCS MÉTIER (BUILD FIELDS & RELATIONS)
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Construit les FieldDefinition depuis la table (UI multiselect + types).
     *
     * @return list<DField>
     */
    private function buildFillableFromTable(string $table): array
    {
        if (! Schema::hasTable($table)) {
            error("Table « {$table} » introuvable.");

            return [];
        }

        $cols = Schema::getColumnListing($table);
        $cols = array_values(array_filter($cols, fn ($c) => ! in_array($c, self::TECH_COLS, true)));

        if ($cols === []) {
            warning("Aucune colonne sélectionnable pour « {$table} ».");

            return [];
        }

        $options = array_combine($cols, $cols);
        $chosen = multiselect(
            label: "Colonnes de « {$table} »",
            options: $options,
            default: array_values($options),
            required: false,
            scroll: self::UI_SCROLL
        );
        $chosen = is_array($chosen) ? $chosen : [];

        // métadonnées si disponibles (doctrine/dbal)
        $byName = [];
        try {
            foreach (Schema::getColumns($table) as $c) {
                if (isset($c['name'])) {
                    $byName[$c['name']] = $c;
                }
            }
        } catch (\Throwable) {
            // silencieux : fallback
        }

        $preview = [];

        $list = array_map(function (string $name) use (&$preview, $byName, $table) {
            $colData = $byName[$name] ?? null;

            $sqlType = $colData['type'] ?? $this->safeColumnType($table, $name) ?? 'mixed';
            $default = $colData['default'] ?? null;
            $phpType = $this->sqlToPhpType((string) $sqlType);

            $preview[] = [$name, $sqlType, $default];

            $ftype = $this->mapPhpTypeToFieldType($phpType);

            return DField::make($name, $ftype)->default($default);
        }, $chosen);

        table(['col name', 'sql type', 'default'], $preview);

        if (confirm('Personnaliser des types ?', false)) {
            $list = $this->customizeTypesUI($table, $list);
        }

        return $list;
    }

    /**
     * @param  list<array{name:string,type:string,defaultValue:mixed,customizedType:string}>  $fillable
     * @return list<array<string,mixed>>
     */
    private function collectBelongsToRelationsUI(array $fillable): array
    {
        $fkFields = array_values(array_filter($fillable, fn ($f) => Str::endsWith($f['name'], '_id')));
        if ($fkFields === []) {
            return [];
        }

        info('Champs potentiels pour des relations belongsTo :');
        $options = [];
        foreach ($fkFields as $f) {
            $options[$f['name']] = $f['name'];
        }
        $options[self::MENU_NEXT] = self::MENU_NEXT;

        $rels = [];
        while ($options !== []) {
            $field = select(
                label: 'Sélectionner un champ (ou #Suivant)',
                options: $options,
                default: array_key_first($options),
                scroll: self::UI_SCROLL
            );

            if ($field === self::MENU_NEXT) {
                break;
            }

            $rels[] = $this->buildBelongRelationDataUI($field);
            unset($options[$field]);
        }

        return $rels;
    }

    /** @return array<string,mixed> */
    private function editSingleRelationUI(array $rel): array
    {
        $rel['name'] = trim(text('Nom de la relation', 'Actuel : '.($rel['name'] ?? ''))) ?: ($rel['name'] ?? '');
        $rel['foreignKey'] = trim(text('Foreign key', 'Actuel : '.($rel['foreignKey'] ?? ''))) ?: ($rel['foreignKey'] ?? '');
        $rel['table'] = trim(text('Table liée', 'Actuel : '.($rel['table'] ?? ''))) ?: ($rel['table'] ?? '');
        $rel['ownerKey'] = trim(text('Owner key', 'Actuel : '.($rel['ownerKey'] ?? 'id'))) ?: ($rel['ownerKey'] ?? 'id');
        $rel['moduleName'] = trim(text('Module cible', 'Actuel : '.($rel['moduleName'] ?? $this->moduleName))) ?: ($rel['moduleName'] ?? $this->moduleName);
        $rel['externalModule'] = ($rel['moduleName'] ?? $this->moduleName) !== $this->moduleName;
        $rel['isParentHasMany'] = confirm('Définir hasMany dans le parent ?', (bool) ($rel['isParentHasMany'] ?? false));

        return $rel;
    }

    /** @return array<string,mixed> */
    private function buildBelongRelationDataUI(string $field): array
    {
        $isExternal = confirm('Relation vers un autre module ?', false);
        $targetModule = $isExternal ? $this->askModule(false) : $this->moduleName;
        $targetGen = $isExternal ? new ModuleGenerator($targetModule) : $this->moduleGen;

        $targetModel = $this->askModel(false, $targetGen);
        $targetModelGen = new ModelGen(Str::kebab($targetModel), $targetModule);
        $targetTable = $targetModelGen->getTableName();

        $ownerKey = $this->askTableColumn(tableName: $targetTable, hiddenIdKeys: false) ?? 'id';

        $defaultName = Str::camel($targetTable);
        $relName = trim(text('Nom de la relation', "Par défaut : {$defaultName}"));
        $relName = $relName !== '' ? $relName : $defaultName;

        $isParentHasMany = confirm('Définir hasMany dans le parent ?', false);

        return [
            'type' => 'belongsTo',
            'foreignKey' => $field,
            'table' => $targetTable,
            'ownerKey' => $ownerKey,
            'name' => $relName,
            'moduleName' => $targetModule,
            'externalModule' => $targetModule !== $this->moduleName,
            'model' => [
                'name' => $targetModel,
                'namespace' => $targetGen->getModelNameSpace(),
                'fqcn' => $targetGen->getModelNameSpace().'\\'.$targetModel,
                'path' => $targetGen->getModelsDirectoryPath().'/'.$targetModel.'.php',
            ],
            'isParentHasMany' => $isParentHasMany,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // UTILITAIRES
    // ─────────────────────────────────────────────────────────────────────────────

    private function safeColumnType(string $table, string $col): ?string
    {
        try {
            return Schema::getColumnType($table, $col);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Mappe un type “php” (issu de sqlToPhpType) vers l’enum FieldType de l’ORM.
     */
    private function mapPhpTypeToFieldType(string $phpType): FieldType
    {
        return match ($phpType) {
            'int', 'integer' => FieldType::Integer,
            'float', 'double' => FieldType::Float,
            'bool', 'boolean' => FieldType::Boolean,
            'date' => FieldType::Date,
            'datetime' => FieldType::DateTime,
            'json', 'array' => FieldType::Json,
            default => FieldType::String,
        };
    }

    /**
     * @param  list<string>|array<int,string>  $list
     * @return array<string,string>
     */
    private function toOptions(array $list, bool $preserveKeys = true): array
    {
        $opts = [];
        foreach ($list as $k => $v) {
            $label = is_scalar($v) || $v === null ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
            $key = ($preserveKeys && is_string($k) && $k !== '') ? (string) $k : (string) $label;
            if ($key === '') {
                $key = md5($label);
            }
            $opts[$key] = (string) $label;
        }

        return $opts;
    }

    private function askModule(bool $managerModule = true): string
    {
        $modules = ModuleGenerator::getModuleList();
        if ($managerModule) {
            $modules['deleteModule'] = '# Supprimer le module #';
            $modules['addModule'] = '# Créer le module #';
        }

        return select(
            label: 'Sélectionner le module',
            options: $this->toOptions($modules, false),
            default: array_key_first($modules),
            scroll: self::UI_SCROLL
        );
    }

    private function askModel(bool $managerModel = true, ?ModuleGenerator $moduleGen = null): string
    {
        $moduleGen ??= $this->moduleGen;
        $models = $moduleGen->getModels() ?? [];
        if ($managerModel) {
            $models['addModel'] = '# Créer un modèle #';
            $models['deleteModel'] = '# Supprimer un modèle #';
        }

        return select(
            label: 'Sélectionner le modèle',
            options: $this->toOptions($models, false),
            default: array_key_first($models),
            scroll: self::UI_SCROLL
        );
    }

    /**
     * @return array<int,string>|string|null
     */
    private function askTableColumn(string $tableName, bool $multiSelect = false, bool $allSelected = false, bool $hiddenIdKeys = true): array|string|null
    {
        if (! Schema::hasTable($tableName)) {
            error("La table « {$tableName} » est introuvable.");

            return $multiSelect ? [] : null;
        }

        $columns = Schema::getColumnListing($tableName);
        if ($hiddenIdKeys) {
            $columns = array_values(array_filter($columns, fn ($c) => ! in_array($c, self::TECH_COLS, true)));
        }

        if ($columns === []) {
            warning("La table « {$tableName} » ne contient aucune colonne sélectionnable.");

            return $multiSelect ? [] : null;
        }

        $options = array_combine($columns, $columns);

        if ($multiSelect) {
            $default = $allSelected ? array_values($options) : [];

            return multiselect(
                label: "Sélectionner des colonnes ( {$tableName} )",
                options: $options,
                default: $default,
                scroll: self::UI_SCROLL,
                required: false
            );
        }

        return select(
            label: "Sélectionner une colonne ( {$tableName} )",
            options: $options,
            default: array_key_first($options),
            scroll: self::UI_SCROLL
        );
    }

    /**
     * Personnalise les types (enum FieldType) pour une sélection de champs.
     *
     * @param  list<DField>  $fillable
     * @return list<DField>
     */
    private function customizeTypesUI(string $table, array $fillable): array
    {
        $names = array_map(fn (DField $f) => $f->name, $fillable);
        $choices = array_combine($names, $names);

        $toEdit = multiselect(
            label: "Colonnes à typer ( {$table} )",
            options: $choices,
            required: false,
            scroll: self::UI_SCROLL
        );

        $toEdit = is_array($toEdit) ? $toEdit : [];
        if ($toEdit === []) {
            return $fillable;
        }

        $index = array_flip($names);
        foreach ($toEdit as $name) {
            $type = select("Type pour {$name}", ['string', 'text', 'integer', 'float', 'boolean', 'date', 'datetime', 'json'], 'string');
            $fillable[$index[$name]]->type = FieldType::from($type);
            // Optionnel : customizedType séparé
            if (confirm("Définir un customizedType pour {$name} ?", false)) {
                $custom = text('customizedType (laisser vide pour aucun)', '');
                $fillable[$index[$name]]->customizedType = $custom !== '' ? $custom : '';
            }
        }

        return $fillable;
    }
}
