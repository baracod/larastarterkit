<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Backend\Model;

use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use App\Generator\Backend\Model\ModelGen;
use App\Generator\ModuleGenerator;
use App\Generator\Traits\SqlConversion;

use function Laravel\Prompts\{
    info,
    warning,
    error,
    note,
    select,
    multiselect,
    text,
    confirm,
    table
};

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
        $this->moduleGen  = new ModuleGenerator($moduleName);

        // Emplacement du JSON : ModuleData/{module}.json
        $dir = $jsonDir ?:  base_path("ModuleData");
        if (!File::exists($dir)) {
            File::makeDirectory($dir, 0775, true);
        }
        $this->jsonPath = $dir . DIRECTORY_SEPARATOR . Str::kebab($moduleName) . '.json';

        $this->ensureFile();
    }

    /**
     * Crée / renvoie la structure JSON initiale si absent.
     */
    private function ensureFile(): void
    {
        if (!File::exists($this->jsonPath)) {
            $data = ['module' => $this->moduleName, 'models' => new \stdClass()];
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
        $raw = File::get($this->jsonPath);
        $data = json_decode($raw ?: '{}', true) ?: [];
        $data['module'] = $data['module'] ?? $this->moduleName;
        $data['models'] = $data['models'] ?? [];
        return $data;
    }

    /**
     * Sauvegarde l’état sur disque.
     *
     * @param array{module:string,models:array<string,array<string,mixed>>} $data
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

        while (true) {
            $models = $data['models'];
            $options = [];

            // Liste des modèles existants
            foreach ($models as $key => $def) {
                $options[$key] = $def['name'] . "  ·  " . ($def['tableName'] ?? '-');
            }

            // Actions globales
            $options['__create'] = '➕ Créer un modèle';
            $options['__savequit'] = '💾 Enregistrer et quitter';

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
                }
                continue;
            }

            // Éditer un modèle existant
            $edited = $this->editModelUI($data['models'][$choice]);
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
     * @param array{module:string,models:array<string,array<string,mixed>>} $data
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
            . '/' . $name . '.php';
        $fqcn = $namespace . '\\' . $name;
        $key  = Str::kebab($name);

        // Build fillable
        $fillable = $this->buildFillableFromTable($table);

        // Relations belongsTo (optionnelles)
        $relations = $this->collectBelongsToRelationsUI($fillable);

        $def = $this->defaultModelDef($name, $key, $namespace, $table, $path, $fqcn, $relations, $fillable);

        // Petit réglage meta (facultatif immédiat)
        if (confirm('Configurer maintenant les indicateurs backend/frontend ?', default: false)) {
            $def = $this->configureMetaUI($def);
        }

        info("Modèle « {$name} » prêt.");
        return $def;
    }

    // ─────────────────────────────────────────────────────────────────────────────
    // ÉDITION
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * UI d’édition d’un modèle.
     *
     * @param array<string,mixed> $def
     * @return array<string,mixed>|null null => supprimé
     */
    private function editModelUI(array $def): ?array
    {
        while (true) {
            $summary = [
                ['Nom', $def['name'] ?? ''],
                ['Key', $def['key'] ?? ''],
                ['Table', $def['tableName'] ?? ''],
                ['Namespace', $def['namespace'] ?? ''],
                ['FQCN', $def['fqcn'] ?? ''],
                ['Path', $def['path'] ?? ''],
                ['Fillable', (string) count($def['fillable'] ?? [])],
                ['Relations', (string) count($def['relations'] ?? [])],
            ];
            table(headers: ['Champ', 'Valeur'], rows: $summary);

            // ajouter le model s'il n'existe pas et si existe effacer, remettre à jour
            $choice = select(
                'Action',
                [
                    'rename' => 'Renommer / Changer table',
                    'fillable' => self::MENU_EDIT . ' les colonnes',
                    'relations' => self::MENU_EDIT . ' les relations',
                    'meta' => self::MENU_EDIT . ' backend/frontend',
                    self::MENU_SAVE => self::MENU_SAVE,
                    self::MENU_DELETE => self::MENU_DELETE,
                    self::MENU_BACK => self::MENU_BACK,
                ],
                default: self::MENU_SAVE,
                scroll: self::UI_SCROLL
            );

            if ($choice === self::MENU_BACK) {
                return $def;
            }

            if ($choice === self::MENU_DELETE) {
                if (confirm('Confirmer la suppression ?', default: false)) {
                    return null;
                }
                continue;
            }

            if ($choice === self::MENU_SAVE) {
                info('Modèle sauvegardé en mémoire (écriture disque au menu principal).');
                return $def;
            }

            if ($choice === 'rename') {
                $def = $this->renameModelUI($def);
                continue;
            }

            if ($choice === 'fillable') {
                $def = $this->editFillableUI($def);
                continue;
            }

            if ($choice === 'relations') {
                $def = $this->editRelationsUI($def);
                continue;
            }

            if ($choice === 'meta') {
                $def = $this->configureMetaUI($def);
                continue;
            }
        }
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
            $def['key']  = Str::kebab($name);
            $def['path'] = rtrim(dirname((string) $def['path']), '/')
                . '/' . $name . '.php';
            $def['fqcn'] = ($def['namespace'] ?? '') . '\\' . $name;
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
     * @param array<string,mixed> $def
     * @return array<string,mixed>
     */
    private function editRelationsUI(array $def): array
    {
        $rels = $def['relations'] ?? [];
        while (true) {
            table(
                headers: ['#', 'type', 'name', 'foreignKey', 'table', 'module'],
                rows: array_map(
                    fn($r, $i) => [$i, $r['type'] ?? '', $r['name'] ?? '', $r['foreignKey'] ?? '', $r['table'] ?? '', $r['moduleName'] ?? ''],
                    $rels,
                    array_keys($rels)
                )
            );

            $choice = select('Relations', [
                '__add'   => self::MENU_ADD,
                '__edit'  => self::MENU_EDIT,
                '__del'   => self::MENU_DELETE,
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
                if (!isset($rels[$idx])) {
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
        $def['backend']  = $def['backend']  ?? ['hasController' => false, 'hasRequest' => false, 'hasRoute' => false, 'hasPermission' => false];
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
            'casl' => ['create' => false, 'read' => false, 'update' => false, 'delete' => false, 'access' => false]
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
    // BLOCS MÉTIER
    // ─────────────────────────────────────────────────────────────────────────────

    /**
     * Construit la liste fillable depuis la table (UI multiselect + types).
     * @return list<array{name:string,type:string,defaultValue:mixed,customizedType:string}>
     */
    private function buildFillableFromTable(string $table): array
    {
        if (!Schema::hasTable($table)) {
            error("Table « {$table} » introuvable.");
            return [];
        }

        $cols = Schema::getColumnListing($table);
        $cols = array_values(array_filter($cols, fn($c) => !in_array($c, self::TECH_COLUMNS, true)));

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
     * @param list<array{name:string,type:string,defaultValue:mixed,customizedType:string}> $fillable
     * @return list<array<string,mixed>>
     */
    private function collectBelongsToRelationsUI(array $fillable): array
    {
        $fkFields = array_values(array_filter($fillable, fn($f) => Str::endsWith($f['name'], '_id')));

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
        $rel['name']       = trim(text('Nom de la relation', "Actuel : " . ($rel['name'] ?? ''))) ?: ($rel['name'] ?? '');
        $rel['foreignKey'] = trim(text('Foreign key', "Actuel : " . ($rel['foreignKey'] ?? ''))) ?: ($rel['foreignKey'] ?? '');
        $rel['table']      = trim(text('Table liée', "Actuel : " . ($rel['table'] ?? ''))) ?: ($rel['table'] ?? '');
        $rel['ownerKey']   = trim(text('Owner key', "Actuel : " . ($rel['ownerKey'] ?? 'id'))) ?: ($rel['ownerKey'] ?? 'id');
        $rel['moduleName'] = trim(text('Module cible', "Actuel : " . ($rel['moduleName'] ?? $this->moduleName))) ?: ($rel['moduleName'] ?? $this->moduleName);
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

        $ownerKey = $this->askTableColumn($targetTable) ?? 'id';

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
                'fqcn' => $targetGen->getModelNameSpace() . '\\' . $targetModel,
                'path' => $targetGen->getModelsDirectoryPath() . '/' . $targetModel . '.php',
            ],
            'isParentHasMany' => $isParentHasMany,
        ];
    }

    /**
     * Applique une personnalisation de types via UI.
     *
     * @param list<array{name:string,type:string,defaultValue:mixed,customizedType:string}> $fillable
     * @return list<array{name:string,type:string,defaultValue:mixed,customizedType:string}>
     */
    private function customizeTypesUI(string $table, array $fillable): array
    {
        $names = array_map(fn($f) => $f['name'], $fillable);
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
            $modules['addModule']    = '# Créer le module #';
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
        if (!Schema::hasTable($tableName)) {
            error("La table « {$tableName} » est introuvable.");
            return $multiSelect ? [] : null;
        }

        $columns = Schema::getColumnListing($tableName);
        if ($hiddenIdKeys) {
            $columns = array_values(array_filter($columns, fn($c) => !in_array($c, self::TECH_COLUMNS, true)));
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
     * @param list<array{name:string,type:string,defaultValue:mixed,customizedType:string}> $fillable
     * @param list<array<string,mixed>> $relations
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
            'name'       => $name,
            'key'        => $key,
            'namespace'  => $namespace,
            'tableName'  => $table,
            'moduleName' => $this->moduleName,
            'fillable'   => $fillable,
            'relations'  => $relations,
            'path'       => $path,
            'fqcn'       => $fqcn,
            'backend' => [
                'hasController' => false,
                'hasRequest'    => false,
                'hasRoute'      => false,
                'hasPermission' => false,
            ],
            'frontend' => [
                'hasType'               => false,
                'hasApi'                => false,
                'hasLang'               => false,
                'hasAddOrEditComponent' => false,
                'hasReadComponent'      => false,
                'hasIndex'              => false,
                'hasMenu'               => false,
                'hasPermission'         => false,
                'fields'                => [],
                'casl' => [
                    'create' => false,
                    'read'   => false,
                    'update' => false,
                    'delete' => false,
                    'access' => false,
                ],
            ],
        ];
    }
}
