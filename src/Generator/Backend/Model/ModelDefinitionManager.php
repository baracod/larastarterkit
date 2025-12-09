<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Model;

use Baracod\Larastarterkit\Generator\Backend\Http\ApiDocGen;
use Baracod\Larastarterkit\Generator\Backend\Http\ControllerGen;
use Baracod\Larastarterkit\Generator\Backend\Http\RouteGen;
use Baracod\Larastarterkit\Generator\Frontend\TypeScriptGeneratorFromJson;
use Baracod\Larastarterkit\Generator\ModuleGenerator;
use Baracod\Larastarterkit\Generator\Traits\SqlConversion;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\info;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;
use function Laravel\Prompts\table;
use function Laravel\Prompts\text;
use function Laravel\Prompts\warning;

/**
 * Gère un fichier JSON {module, models:{...}} et fournit une UI pour créer/éditer
 * les définitions de modèles (champs fillable, relations, meta backend/frontend).
 */
final class ModelDefinitionManager
{
    use SqlConversion;

    private const UI_SCROLL = 20;

    private const TECH_COLUMNS = ['id', 'created_at', 'updated_at', 'deleted_at', 'uuid'];

    private const MENU_BACK = '« Retour';

    private const MENU_SAVE = '💾 Enregistrer';

    private const MENU_DELETE = '🗑️ Supprimer';

    private const MENU_ADD = '➕ Ajouter';

    private const MENU_EDIT = '✏️ Éditer';

    private const MENU_NEXT = '#Suivant';

    private string $moduleName;

    private ModuleGenerator $moduleGen;

    private string $jsonPath;

    public function __construct(string $moduleName, ?string $jsonDir = null)
    {
        $this->moduleName = $moduleName;
        $this->moduleGen = new ModuleGenerator($moduleName);

        // Emplacement du JSON : ModuleData/{module}.json
        $dir = $jsonDir ?: base_path('ModuleData');
        if (! File::exists($dir)) {
            File::makeDirectory($dir, 0775, true);
        }
        $this->jsonPath = $dir.DIRECTORY_SEPARATOR.Str::kebab($moduleName).'.json';

        $this->ensureFile();
    }

    /**
     * Crée / renvoie la structure JSON initiale si absent.
     */
    private function ensureFile(): void
    {
        if (! File::exists($this->jsonPath)) {
            $data = ['module' => $this->moduleName, 'models' => new \stdClass];
            File::put($this->jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        }
    }

    /**
     * Charge le JSON en tableau associatif.
     *
     * @return array{module:string,models:array<string,array<string,mixed>>}
     */
    public function read(): array
    {
        try {
            $raw = File::get($this->jsonPath);
            $data = json_decode($raw ?: '{}', true) ?: [];
            $data['module'] = $data['module'] ?? $this->moduleName;
            $data['models'] = $data['models'] ?? [];

            return $data;
        } catch (\Throwable $th) {
            // throw $th;
            info($th->getMessage());

            return ['module' => $this->moduleName, 'models' => []];
        }
    }

    /**
     * Sauvegarde l’état sur disque.
     *
     * @param  array{module:string,models:array<string,array<string,mixed>>}  $data
     */
    public function write(array $data): void
    {
        File::put($this->jsonPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    }

    public function getJsonPath(): string
    {
        return $this->jsonPath;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // UI PRINCIPALE
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Menu principal : créer/éditer/supprimer un modèle.
     */
    public function interactive(): array
    {
        $data = $this->read();
        $apiDocGen = new ApiDocGen('./swagger.json');
        $apiDocGen->build($data);
        while (true) {
            $models = $data['models'];
            $options = [];

            // Liste des modèles existants
            foreach ($models as $key => $def) {
                $options[$key] = $def['name'].'  ·  '.($def['tableName'] ?? '-');
            }

            // Actions globales
            $options['__create'] = '➕ Créer un modèle';
            // $options['__savequit'] = '💾 Enregistrer et quitter';

            $choice = select(
                label: "Module « {$this->moduleName} » — Modèles",
                options: $options,
                default: array_key_first($options),
                scroll: self::UI_SCROLL
            );

            if ($choice === '__savequit') {
                $this->write($data);
                info("Sauvegardé dans : {$this->jsonPath}");

                return $data;
            }

            if ($choice === '__create') {
                $def = $this->createModelUI($data);
                if ($def !== null) {
                    $data['models'][$def['key']] = $def;

                    info("La structure du Modèle « {$def['name']} » ajouté.");
                    // generation de fichier php.
                    $this->genPhpClass($def, $data);
                }

                continue;
            }

            // Éditer un modèle existant
            // $edited = $this->editModelUI($data['models'][$choice]);
            $edited = $data['models'][$choice];
            $this->genPhpClass($edited, $data);

            if ($edited === null) {
                // Suppression
                unset($data['models'][$choice]);
                info("Modèle « {$choice} » supprimé.");
            } else {
                $data['models'][$edited['key']] = $edited;
                // si renommage de key, supprimer l’ancienne
                if ($edited['key'] !== $choice) {
                    unset($data['models'][$choice]);
                }
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // CRÉATION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * UI de création : table → nom → fillable → relations → meta.
     *
     * @param  array{module:string,models:array<string,array<string,mixed>>}  $data
     * @return array<string,mixed>|null
     */
    private function createModelUI(array $data): ?array
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
        $path = rtrim($this->moduleGen->getModelsDirectoryPath(), '/')
            .'/'.$name.'.php';
        $fqcn = $namespace.'\\'.$name;
        $key = Str::kebab($name);

        // Build fillable
        $fillable = $this->buildFillableFromTable($table);

        // Relations belongsTo (optionnelles)
        $relations = $this->collectBelongsToRelationsUI($fillable);

        $def = $this->defaultModelDef($name, $key, $namespace, $table, $path, $fqcn, $relations, $fillable);

        // Petit réglage meta (facultatif immédiat)
        if (confirm('Configurer maintenant les indicateurs backend/frontend ?', default: false)) {
            $def = $this->configureMetaUI($def);
        }

        $data['models'][$def['key']] = $def;
        $this->write($data);
        info("Modèle « {$name} » prêt.");

        return $def;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ÉDITION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * UI d’édition d’un modèle.
     *
     * @param  array<string,mixed>  $def
     * @return array<string,mixed>|null null => supprimé
     */
    private function editModelUI(array $def): ?array
    {
        while (true) {
            $backendMeta = $def['backend'];

            $summary = [
                ['Nom', $def['name'] ?? ''],
                ['Key', $def['key'] ?? ''],
                ['Table', $def['tableName'] ?? ''],
                ['Namespace', $def['namespace'] ?? ''],
                ['FQCN', $def['fqcn'] ?? ''],
                ['Fillable', (string) count($def['fillable'] ?? [])],
                ['Relations', (string) count($def['relations'] ?? [])],
                ['----------', '-----------------'],
                ['Model', $backendMeta['hasModel'] ? 'oui' : 'non'],
                ['Controller',  $backendMeta['hasController'] ? 'oui' : 'non'],
                ['Request',  $backendMeta['hasRequest'] ? 'oui' : 'non'],
                ['Route',  $backendMeta['hasRoute'] ? 'oui' : 'non'],

            ];
            table(headers: ['Champ', 'Valeur'], rows: $summary);

            $A_CREATE_MODEL = '__create_model';
            $A_CREATE_CONTROLLER = '__create_controller';
            $A_CREATE_REQUEST = '__create_request';
            $A_UPDATE_ROUTE = '__update_route';

            if (! $backendMeta['hasModel']) {
                $actions[$A_CREATE_MODEL] = 'Créer un modèle';
            }
            if (! $backendMeta['hasController']) {
                $actions[$A_CREATE_CONTROLLER] = 'Créer un contrôleur';
            }
            if (! $backendMeta['hasRequest']) {
                $actions[$A_CREATE_REQUEST] = 'Créer une requête';
            }
            if (! $backendMeta['hasRoute']) {
                $actions[$A_UPDATE_ROUTE] = 'Mettre à jour une route';
            }

            $actions[self::MENU_BACK] = self::MENU_BACK;

            $choice = select(
                'Action',
                $actions,
                default: self::MENU_SAVE,
                scroll: self::UI_SCROLL
            );

            dd($choice);
            if ($choice == $A_CREATE_MODEL) {
                // Génération du modèle
                new ModelGen($def['key'], $def['moduleName']);
                info("Le model {$def['name']} est créé");

                // Assure l'existence des clés et FAIS une assignation
                $def['backend'] = $def['backend'] ?? [];
                $def['backend']['hasModel'] = true;

                // Mets à jour la structure globale
                $data['models'][$def['key']] = $def;
                $this->write($data);
                unset($actions[$choice]);
                break;
            }

            if ($choice == $A_CREATE_CONTROLLER) {
                new ControllerGen(new ModelGen($def['key'], $def['moduleName']));
                $def['backend'] = $def['backend'] ?? [];
                $def['backend']['hasController'] = true;
                info("Le contrôleur {$def['name']} est créé");
                unset($actions[$choice]);
            }

            if ($choice === self::MENU_BACK) {
                return $def;
            }
        }

        return $def;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // SOUS-MENUS
    // ─────────────────────────────────────────────────────────────────────────────

    /** @param array<string,mixed> $def */
    private function renameModelUI(array $def): array
    {
        $name = trim(text('Nom du modèle', "Actuel : {$def['name']}"));
        if ($name !== '') {
            $def['name'] = $name;
            $def['key'] = Str::kebab($name);
            $def['path'] = rtrim(dirname((string) $def['path']), '/')
                .'/'.$name.'.php';
            $def['fqcn'] = ($def['namespace'] ?? '').'\\'.$name;
        }

        $tables = $this->moduleGen->getTableList();
        $table = select(
            label: 'Table',
            options: $this->toOptions($tables, false),
            default: $def['tableName'] ?? array_key_first($tables),
            scroll: self::UI_SCROLL
        );
        $def['tableName'] = $table;

        return $def;
    }

    /** @param array<string,mixed> $def */
    private function editFillableUI(array $def): array
    {
        $table = (string) $def['tableName'];
        $current = $def['fillable'] ?? [];

        // 1) Rechoisir colonnes
        $fresh = $this->buildFillableFromTable($table);

        // 2) Fusion douce : conserver customizedType existants si même nom
        $map = [];
        foreach ($current as $f) {
            $map[$f['name']] = $f['customizedType'] ?? '';
        }
        foreach ($fresh as &$f) {
            if (($map[$f['name']] ?? '') !== '') {
                $f['customizedType'] = $map[$f['name']];
            }
        }
        unset($f);

        // 3) Édition des types personnalisés (option)
        if (confirm('Changer le type de colonnes ?', default: false)) {
            $fresh = $this->customizeTypesUI($table, $fresh);
        }

        $def['fillable'] = $fresh;

        return $def;
    }

    /**
     * @param  array<string,mixed>  $def
     * @return array<string,mixed>
     */
    private function editRelationsUI(array $def): array
    {
        $rels = $def['relations'] ?? [];
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
                $def['relations'] = array_values($rels);

                return $def;
            }

            if ($choice === '__add') {
                $fillable = $def['fillable'] ?? [];
                $new = $this->collectBelongsToRelationsUI($fillable);
                $rels = array_values(array_merge($rels, $new));

                continue;
            }

            if ($choice === '__edit') {
                if ($rels === []) {
                    warning('Aucune relation.');

                    continue;
                }
                $idx = (int) text('Index de la relation à éditer (voir tableau ci-dessus)');
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

    /** @param array<string,mixed> $def */
    private function configureMetaUI(array $def): array
    {
        $def['backend'] = $def['backend'] ?? ['hasController' => false, 'hasRequest' => false, 'hasRoute' => false, 'hasPermission' => false];
        $def['frontend'] = $def['frontend'] ?? [
            'hasType' => false,
            'hasApi' => false,
            'hasLang' => false,
            'hasAddOrEditComponent' => false,
            'hasReadComponent' => false,
            'hasIndex' => false,
            'hasMenu' => false,
            'hasPermission' => false,
            'fields' => [],
            'casl' => ['create' => false, 'read' => false, 'update' => false, 'delete' => false, 'access' => false],
        ];

        // backend flags
        foreach (['hasController', 'hasRequest', 'hasRoute', 'hasPermission'] as $flag) {
            $def['backend'][$flag] = confirm("Backend · {$flag} ?", (bool) ($def['backend'][$flag] ?? false));
        }

        // frontend flags
        foreach (['hasType', 'hasApi', 'hasLang', 'hasAddOrEditComponent', 'hasReadComponent', 'hasIndex', 'hasMenu', 'hasPermission'] as $flag) {
            $def['frontend'][$flag] = confirm("Frontend · {$flag} ?", (bool) ($def['frontend'][$flag] ?? false));
        }

        // CASL
        foreach (['create', 'read', 'update', 'delete', 'access'] as $perm) {
            $def['frontend']['casl'][$perm] = confirm("CASL · {$perm} ?", (bool) ($def['frontend']['casl'][$perm] ?? false));
        }

        return $def;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    //  CREATION DE CLASSES PHP
    // ─────────────────────────────────────────────────────────────────────────────
    // region CREATION DE CLASSES PHP
    private function genPhpClass(array $entityStructure, array $data): void
    {
        // Actions (valeurs stables)
        $A_NEXT = self::MENU_NEXT ?? '__next__';
        $A_CREATE_MODEL = '__create_model';
        $A_CREATE_CONTROLLER = '__create_controller';
        $A_CREATE_REQUEST = '__create_request'; // réservé/optionnel
        $A_UPDATE_ROUTE = '__update_route';
        $A_API_REST = '__api_rest';
        $A_FRONTEND = '__frontend';

        // Normalisation des métadonnées
        $entityStructure['backend'] = $entityStructure['backend'] ?? [];
        $entityStructure['moduleName'] = $entityStructure['moduleName'] ?? ($this->moduleName ?? null);
        $entityStructure['key'] = $entityStructure['key'] ?? Str::snake($entityStructure['name'] ?? 'Model');
        $entityStructure['name'] = $entityStructure['name'] ?? Str::studly($entityStructure['key']);

        $name = $entityStructure['name'];
        $key = $entityStructure['key'];

        // Menu dynamique
        $options = [];
        if (empty($entityStructure['backend']['hasModel'])) {
            $options[$A_CREATE_MODEL] = "Générer le modèle : {$name}.php";
        }
        if (empty($entityStructure['backend']['hasController'])) {
            $options[$A_CREATE_CONTROLLER] = "Générer le contrôleur : {$name}Controller.php";
        }
        if (empty($entityStructure['backend']['hasRoute'])) {
            $options[$A_UPDATE_ROUTE] = "Ajouter la route pour {$name}Controller.php";
        }

        // Toujours proposés
        $options[$A_API_REST] = 'Générer API REST (modèle + contrôleur + route)';
        $options[$A_FRONTEND] = "Générer le frontend REST : {$name}";
        $options[$A_NEXT] = 'Continuer';

        while (true) {
            $action = select('Que veux-tu générer ?', $options);

            // Si select() renvoie le label, on remappe vers la clé d’action
            if (! array_key_exists($action, $options)) {
                $action = array_search($action, $options, true) ?: $A_NEXT;
            }

            switch ($action) {
                case $A_CREATE_MODEL:
                    $this->generateModel($entityStructure, $data);
                    $options = $this->maybeUnset($options, $A_CREATE_MODEL);
                    break;

                case $A_CREATE_CONTROLLER:
                    $this->generateController($entityStructure, $data);
                    $options = $this->maybeUnset($options, $A_CREATE_CONTROLLER);
                    break;

                case $A_UPDATE_ROUTE:
                    $this->updateRoute($entityStructure, $data);
                    break;

                case $A_API_REST:
                    $this->generateApiRest($entityStructure, $data);
                    // Les deux options deviennent obsolètes après le bundle
                    $options = $this->maybeUnset($options, $A_CREATE_MODEL, $A_CREATE_CONTROLLER);
                    break;

                case $A_FRONTEND:
                    // À implémenter selon votre pipeline
                    $this->generateFrontend($entityStructure, $data);
                    break;

                case $A_NEXT:
                default:
                    return; // sortie propre
            }
        }
    }

    /**
     * Génère le modèle si absent, persiste l’état.
     */
    private function generateModel(array &$entity, array &$data): void
    {
        if (! empty($entity['backend']['hasModel'])) {
            info("Le modèle {$entity['name']} existe déjà.");

            return;
        }

        $modelGen = new ModelGen($entity['key'], $entity['moduleName']);
        if ($modelGen->generate()) {
            info("Modèle {$entity['name']} créé.");
            $entity['backend']['hasModel'] = true;
            $this->persistEntity($entity, $data);
        }
    }

    /**
     * Génère le contrôleur si absent, persiste l’état.
     */
    private function generateController(array &$entity, array &$data): void
    {
        if (! empty($entity['backend']['hasController'])) {
            info("Le contrôleur {$entity['name']}Controller existe déjà.");

            return;
        }

        $controllerGen = new ControllerGen(new ModelGen($entity['key'], $entity['moduleName']));
        if ($controllerGen->generate()) {
            info("Contrôleur {$entity['name']}Controller créé.");
            $entity['backend']['hasController'] = true;
            $this->persistEntity($entity, $data);
        }
    }

    /**
     * Ajoute/actualise la ressource API dans routes/api.php du module.
     */
    private function updateRoute(array &$entity, array &$data): void
    {
        $routeGen = new RouteGen($this->moduleGen->getRouteApiPath());
        $routeName = Str::kebab(Str::smartPlural($entity['key']));
        $apiRoute = $routeGen->addApiResource($routeName, "{$entity['name']}Controller", $this->moduleName)['apiRoute'];

        $entity['backend']['hasRoute'] = true;
        $entity['backend']['apiRoute'] = $apiRoute;
        $this->persistEntity($entity, $data);
        info("Route API resource « {$routeName} » mise à jour.");
    }

    /**
     * Bundle : modèle + contrôleur + route (idempotent).
     */
    private function generateApiRest(array &$entity, array &$data): void
    {
        $this->generateModel($entity, $data);
        $this->generateController($entity, $data);
        $this->updateRoute($entity, $data);
    }

    /**
     * Persiste la structure d’entité dans $data et écrit sur disque.
     */
    private function persistEntity(array $entity, array &$data): void
    {
        $key = $entity['key'];
        $data['models'][$key] = $entity;
        $this->write($data);
    }

    /**
     * Retire proprement des clés d’options si elles existent.
     */
    private function maybeUnset(array $options, string ...$keys): array
    {
        foreach ($keys as $k) {
            if (array_key_exists($k, $options)) {
                unset($options[$k]);
            }
        }

        return $options;
    }

    /**
     * Retire proprement des clés d’options si elles existent.
     */
    private function generateFrontend(array &$entity, array &$data): void
    {
        // if (!empty($entity['backend']['hasModel'])) {
        //     info("Le modèle {$entity['name']} existe déjà.");
        //     return;
        // }

        $modelGen = new TypeScriptGeneratorFromJson($entity['key'], $entity['moduleName']);
        if ($modelGen->generate()) {
            info("Modèle {$entity['name']} créé.");
            $entity['backend']['hasModel'] = true;
            $this->persistEntity($entity, $data);
        }
    }
    // endregion CREATION DE CLASSES PHP

    // ─────────────────────────────────────────────────────────────────────────────
    // BLOCS MÉTIER
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Construit la liste fillable depuis la table (UI multiselect + types).
     *
     * @return list<array{name:string,type:string,defaultValue:mixed,customizedType:string}>
     */
    private function buildFillableFromTable(string $table): array
    {
        if (! Schema::hasTable($table)) {
            error("Table « {$table} » introuvable.");

            return [];
        }

        $cols = Schema::getColumnListing($table);
        $cols = array_values(array_filter($cols, fn ($c) => ! in_array($c, self::TECH_COLUMNS, true)));

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

        // Métadonnées (si doctrine/dbal installé)
        $byName = [];
        try {
            foreach (Schema::getColumns($table) as $c) {
                if (isset($c['name'])) {
                    $byName[$c['name']] = $c;
                }
            }
        } catch (\Throwable) {
            // fallback : type "mixed" si indisponible
        }

        $preview = [];
        $fillable = array_map(function (string $name) use (&$preview, $byName, $table) {
            $colData = $byName[$name] ?? null;

            $sqlType = $colData['type'] ?? $this->safeColumnType($table, $name) ?? 'mixed';
            $default = $colData['default'] ?? null;
            $phpType = $this->sqlToPhpType((string) $sqlType);

            $preview[] = [$name, $sqlType, $default];

            return [
                'name' => $name,
                'type' => $phpType,
                'defaultValue' => $default,
                'customizedType' => '',
            ];
        }, $chosen);

        table(['col name', 'sql type', 'default'], $preview);

        // Option : personnaliser types
        if (confirm('Personnaliser des types ?', false)) {
            $fillable = $this->customizeTypesUI($table, $fillable);
        }

        return $fillable;
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

    /**
     * Applique une personnalisation de types via UI.
     *
     * @param  list<array{name:string,type:string,defaultValue:mixed,customizedType:string}>  $fillable
     * @return list<array{name:string,type:string,defaultValue:mixed,customizedType:string}>
     */
    private function customizeTypesUI(string $table, array $fillable): array
    {
        $names = array_map(fn ($f) => $f['name'], $fillable);
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
            $type = select("Type pour {$name}", ['int', 'float', 'string', 'boolean', 'array', 'date', 'json'], 'string');
            $fillable[$index[$name]]['customizedType'] = $type;
        }

        return $fillable;
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

    // Réutilise les helpers Console (demande de module/modèle/colonne)
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
        $models = $moduleGen->getModels();
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
            $columns = array_values(array_filter($columns, fn ($c) => ! in_array($c, self::TECH_COLUMNS, true)));
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
     * @param  list<array{name:string,type:string,defaultValue:mixed,customizedType:string}>  $fillable
     * @param  list<array<string,mixed>>  $relations
     * @return array<string,mixed>
     */
    private function defaultModelDef(
        string $name,
        string $key,
        string $namespace,
        string $table,
        string $path,
        string $fqcn,
        array $relations,
        array $fillable
    ): array {
        return [
            'name' => $name,
            'key' => $key,
            'namespace' => $namespace,
            'tableName' => $table,
            'moduleName' => $this->moduleName,
            'fillable' => $fillable,
            'relations' => $relations,
            'path' => $path,
            'fqcn' => $fqcn,
            'backend' => [
                'hasModel' => false,
                'hasController' => false,
                'hasRequest' => false,
                'hasRoute' => false,
                'hasPermission' => false,
            ],
            'frontend' => [
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
            ],
        ];
    }
}
