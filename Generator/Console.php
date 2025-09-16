<?php

declare(strict_types=1);

namespace App\Generator;

use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;
use App\Generator\Backend\Model\ModelGen;
use App\Generator\ModuleGenerator;
use App\Generator\Traits\SqlConversion;

use function Laravel\Prompts\{
    info,
    text,
    error,
    warning,
    select,
    confirm,
    multiselect,
    note,
    table
};

final class Console
{
    use SqlConversion;
    private ?string $moduleName = null;
    private ?string $action     = null;

    private ?ModuleGenerator $moduleGen = null;
    private ?ModelGen $modelGen         = null;

    /** @var array<int,string> */
    private array $moduleTables = [];

    private string $tableName     = '';
    private string $modelName     = '';
    /** @var array<int,string> */
    private array $fillableFields = [];

    public function main(): void
    {
        $this->setModule();

        if ($this->moduleName === 'deleteModule') {
            info('Suppression du module : à implémenter…');
            return;
        }

        if ($this->moduleName === 'addModule') {
            info('Création du module : à implémenter…');
            return;
        }

        // Lister les modèles + actions
        $models        = $this->moduleGen?->getModels() ?? [];
        $modelsOptions = $this->toOptions($models, preserveKeys: false);
        $modelsOptions['addModel']    = '#  Créer un modèle #';
        $modelsOptions['deleteModel'] = '# Supprimer un modèle #';
        $modelsOptions['readDefModel'] = '# Lire le fichier de definition du modèle #';

        // $this->modelGen = new ModelGen('author', $this->moduleName);



        $this->action = select(
            label: 'Sélectionner le modèle',
            options: $modelsOptions,
            default: array_key_first($modelsOptions),
            scroll: 15
        );

        if ($this->action === 'addModel') {
            $this->createModel();
            return;
        }


        if ($this->action !== 'deleteModel' && $this->action !== 'addModel') {
            info("Modèle sélectionné : {$this->action}");
            $this->modelGen = new ModelGen($this->action, $this->modelName);
            return;
        }


        info("Modèle sélectionné : {$this->action}");
    }



    public function createModel(): void
    {
        if ($this->modelGen !== null) {
            warning('Un générateur de modèle est déjà initialisé.');
            return;
        }

        // 1) Table du module
        $tablesOptions = $this->toOptions($this->moduleTables, preserveKeys: false);
        $this->tableName = select(
            label: 'Sélectionner la table',
            options: $tablesOptions,
            default: array_key_first($tablesOptions),
            scroll: 15
        );

        // 2) Nom du modèle (par défaut: Studly(Singular(table)))
        $suggestedModel = Str::studly(Str::singular($this->tableName));
        $typed = text(
            label: "Entrer le nom du modèle",
            placeholder: "Par défaut : {$suggestedModel}"
        );
        $this->modelName = trim($typed) !== '' ? trim($typed) : $suggestedModel;


        // 4) Colonnes fillable (toutes présélectionnées, colonnes techniques masquées)
        $this->fillableFields = $this->buildFillable($this->tableName);

        // 5) belongsTo potentiels
        $belongToFields = []; // ex: ['user_id', 'role_id', ...]
        $belongToFields = array_filter($this->fillableFields, fn($field) => Str::endsWith($field['name'], '_id'));
        $relations = [];

        if (!empty($belongToFields)) {
            info('Champs potentiels pour des relations belongsTo :');

            $options = $this->toOptions($belongToFields, preserveKeys: false);
            $options['#Suivant'] = '#Suivant';

            while (!empty($options)) {
                $field = select(
                    label: 'Sélectionner un champ (ou #Suivant pour continuer)',
                    options: $options,
                    default: array_key_first($options),
                    scroll: 15
                );

                if ($field === '#Suivant') {
                    break;
                }

                $relations[] = $this->buildBelongRelationData($field);
                unset($options[$field]); // éviter la re-sélection
            }
        }

        // 6) Données du modèle
        $modelData = [
            'name'       => $this->modelName,
            'key'        => Str::kebab($this->modelName, '-'),
            'namespace'  => $this->moduleGen->getModelNameSpace(),
            'tableName'  => $this->tableName,
            'moduleName' => $this->moduleName,
            'fillable'   => $this->fillableFields,
            'relations'  => $relations,
            'path'      => $this->moduleGen->getModelsDirectoryPath() . '/' . ($this->modelName) . '.php',
            'fqcn'      => $this->moduleGen->getModelNameSpace() . '\\' . ($this->modelName),

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

        // 7) Persistance
        ModelGen::writeData($modelData);

        info("Modèle « {$this->modelName} » préparé pour le module « {$this->moduleName} ».");

        $this->modelGen = new ModelGen($modelData["key"], $modelData["moduleName"]);
    }

    public function buildFillable(string $tableName): array
    {
        // 1) Sélection des colonnes (UI)
        $selected = $this->askTableColumn(
            tableName: $tableName,
            multiSelect: true,
            allSelected: true,
            hiddenIdKeys: true,
        );

        // Sécurité
        $selected = is_array($selected) ? $selected : [];
        if ($selected === []) {
            info('Aucune colonne sélectionnée.');
            return $this->fillableFields = [];
        }

        // 2) Récupérer la métadata des colonnes et indexer par nom
        //    (Schema::getColumns nécessite doctrine/dbal)
        $colsData = Schema::getColumns($tableName);
        $byName   = [];
        foreach ($colsData as $c) {
            // ex: ['name' => 'col', 'type' => 'varchar(255)', 'default' => null, ...]
            if (isset($c['name'])) {
                $byName[$c['name']] = $c;
            }
        }

        // 3) Préparer lignes pour l’affichage et construire $fillableFields
        $rows = [];
        $this->fillableFields = array_map(function (string $rawCol) use (&$rows, $byName) {
            // Nettoyage d’un éventuel suffixe ":type"
            $name = Str::before($rawCol, ':');

            // Métadonnées colonne
            $colData = $byName[$name] ?? null;
            $sqlType = $colData['type']    ?? 'mixed';
            $default = $colData['default'] ?? null;

            // Conversion SQL -> PHP (adapte si ta méthode a un autre nom)
            $phpType = $this->sqlToPhpType($sqlType);

            // Pour l’aperçu
            $rows[] = [$name, $sqlType, $default];

            return [
                'name'         => $name,
                'type'         => $phpType,
                'defaultValue' => $default,
                'customizedType'   => '',
            ];
        }, $selected);

        // 4) Affichage console
        table(
            headers: ['col name', 'sql type', 'default value'],
            rows: $rows
        );

        // 5) Option : modifier interactivement le type PHP pour certaines colonnes
        if (confirm('Veux-tu changer le type de colonnes ?', default: false)) {
            $toEditColumns = $this->askTableColumn(
                tableName: $tableName,
                multiSelect: true,
                allSelected: false,
                hiddenIdKeys: true
            );

            $toEditColumns = is_array($toEditColumns) ? $toEditColumns : [];

            if ($toEditColumns !== []) {
                // Indexer $fillableFields par 'name' pour une recherche rapide
                $namesIndex = array_column($this->fillableFields, 'name'); // ex: ['id','title',...]
                foreach ($toEditColumns as $value) {
                    $name = Str::before($value, ':'); // homogénéité

                    // Demande le type cible
                    $type = select(
                        label: "Choisis le type de la colonne '{$name}'",
                        options: ['int', 'float', 'string', 'boolean', 'array', 'date', 'json']
                    );

                    // Retrouver l’index de l’élément à modifier
                    $idx = array_search($name, $namesIndex, true);
                    if ($idx === false) {
                        info("Colonne '{$name}' introuvable parmi les sélectionnées — ignorée.");
                        continue;
                    }

                    $this->fillableFields[$idx]['customizedType'] = $type;
                }
            }
        }

        // 6) Affichage final du tableau complet
        table(
            headers: ['name', 'type', 'default', 'customized'],
            rows: array_map(fn($f) => [
                $f['name'],
                $f['type'],
                $f['defaultValue'],
                $f['customizedType'],
            ], $this->fillableFields)
        );

        // 7) Retourne aussi les champs (utile pour tests / chaînage)
        return $this->fillableFields;
    }

    private function buildBelongRelationData(string $field): array
    {
        $isExternalModule  = confirm('Cette relation appartient-elle à un autre module ?');
        $relatedModuleName = (string)$this->moduleName;
        $relatedModuleGen  = $this->moduleGen;

        if ($isExternalModule) {
            $relatedModuleName = $this->askModule(managerModule: false);
            $relatedModuleGen  = new ModuleGenerator($relatedModuleName);
        }

        // Modèle & table liées
        $relatedModel = $this->askModel(managerModel: false, moduleGen: $relatedModuleGen);
        $relatedModelGen = new ModelGen(Str::kebab($relatedModel), $relatedModuleName);
        $relatedTable = $relatedModelGen->getTableName();

        // Clé propriétaire (ownerKey)
        $ownerKey = $this->askTableColumn($relatedTable) ?? 'id';

        // Nom de la méthode relation (par défaut : camel(table))
        $defaultRelationName = Str::camel($relatedTable);
        $typed = text(
            label: 'Nom de la relation',
            placeholder: "Par défaut : {$defaultRelationName}"
        );
        $relationName = trim($typed) !== '' ? trim($typed) : $defaultRelationName;
        $isParentHasMany = confirm("Voulez-vous définir la relation hasMany dans le parent ?");
        return [
            'type'          => 'belongsTo',
            'foreignKey'    => $field,
            'model'         => [
                'name'      => $relatedModel,
                'namespace' => $relatedModuleGen->getModelNameSpace(),
                "fqcn" => $relatedModuleGen->getModelNameSpace() . '\\' . $relatedModel,
                "path" => $relatedModuleGen->getModelsDirectoryPath() . '/' . ($relatedModel) . '.php',
            ],
            'table'          => $relatedTable,
            'ownerKey'       => $ownerKey,
            'name'           => $relationName,
            'moduleName'     => $relatedModuleName,
            'externalModule' => ($this->moduleName !== $relatedModuleName),
            'isParentHasMany' => $isParentHasMany
        ];
    }

    private function setModule(): void
    {
        $this->moduleName   = $this->askModule();
        $this->moduleGen    = new ModuleGenerator((string)$this->moduleName);
        $this->moduleTables = $this->moduleGen->getTableList();
    }

    /**
     * Sélection d’un module (avec options de gestion si demandé).
     */
    private function askModule(bool $managerModule = true): string
    {
        $modules = ModuleGenerator::getModuleList();

        if ($managerModule) {
            $modules['deleteModule'] = '# Supprimer le module #';
            $modules['addModule']    = '# Créer le module #';
        }

        $options = $this->toOptions($modules, preserveKeys: false);

        return select(
            label: 'Sélectionner le module',
            options: $options,
            default: array_key_first($options),
            hint: 'Le module peut être changé à tout moment.',
            scroll: 15
        );
    }


    /**
     * Sélection d’un modèle dans un module donné.
     */
    private function askModel(bool $managerModel = true, ?ModuleGenerator $moduleGen = null): string
    {
        $moduleGen = $moduleGen ?? $this->moduleGen;

        if ($moduleGen === null) {
            error("Aucun générateur de module n'est disponible.");
            return '';
        }

        $models = $moduleGen->getModels();
        $modelsOptions = $this->toOptions($models, preserveKeys: false);

        if ($managerModel) {
            $modelsOptions['addModel']    = '# Créer un modèle #';
            $modelsOptions['deleteModel'] = '# Supprimer un modèle #';
        }

        return select(
            label: 'Sélectionner le modèle',
            options: $modelsOptions,
            default: array_key_first($modelsOptions),
            scroll: 15
        );
    }

    /**
     * Sélection d’une table dans un module donné.
     */
    private function askTable(?ModuleGenerator $moduleGen = null): string
    {
        $moduleGen = $moduleGen ?? $this->moduleGen;

        if ($moduleGen === null) {
            error("Aucun générateur de module n'est disponible.");
            return '';
        }

        $tables = $moduleGen->getTableList();
        $tablesOptions = $this->toOptions($tables, preserveKeys: false);

        return select(
            label: 'Sélectionner la table',
            options: $tablesOptions,
            default: array_key_first($tablesOptions),
            scroll: 15
        );
    }

    /**
     * Invite pour sélectionner une ou plusieurs colonnes d'une table.
     *
     * @return array<int,string>|string|null
     */
    private function askTableColumn(
        string $tableName,
        bool $multiSelect = false,
        bool $allSelected = false,
        bool $hiddenIdKeys = false,
        bool $showDefaultType = false
    ): array|string|null {
        if (!Schema::hasTable($tableName)) {
            error("La table « {$tableName} » est introuvable.");
            return $multiSelect ? [] : null;
        }

        $columns =  Schema::getColumnListing($tableName);


        if ($hiddenIdKeys) {
            $columns = $this->filterTechnicalColumns($columns);
        }

        if (empty($columns)) {
            warning("La table « {$tableName} » ne contient aucune colonne sélectionnable.");
            return $multiSelect ? [] : null;
        }

        $options = $this->toOptions($columns, preserveKeys: false);
        if ($showDefaultType)
            $options = array_map(function ($col) use ($tableName) {
                return $col . ':' . $this->sqlToPhpType(Schema::getColumnType($tableName, $col, true));
            },  $options);


        if ($multiSelect) {
            $default = $allSelected ? array_keys($options) : [];
            return multiselect(
                label: "Sélectionnez les colonnes de « {$tableName} »",
                options: $options,
                default: $default,
                scroll: 15,
                required: false
            );
        }

        return select(
            label: "Sélectionnez la colonne de « {$tableName} »",
            options: $options,
            default: array_key_first($options),
            scroll: 15
        );
    }

    /**
     * Transforme une liste en options value=>label (clés string) pour Prompts.
     *
     * @param  array<int|string,mixed> $list
     * @return array<string,string>
     */
    private function toOptions(array $list, bool $preserveKeys = true): array
    {
        $opts = [];

        foreach ($list as $k => $v) {
            $label = is_scalar($v) ? (string)$v : json_encode($v, JSON_UNESCAPED_UNICODE);
            $key   = ($preserveKeys && is_string($k) && $k !== '') ? $k : $label;
            $opts[(string)$key] = (string)$label;
        }

        return $opts;
    }

    /**
     * Filtre des colonnes techniques avant sélection.
     *
     * @param  array<int,string> $columns
     * @return array<int,string>
     */
    private function filterTechnicalColumns(array $columns): array
    {
        $blocked = ['id', 'created_at', 'updated_at', 'deleted_at', 'uuid'];
        return array_values(array_filter(
            $columns,
            fn(string $c) => !in_array($c, $blocked, true)
        ));
    }
}
