<?php

namespace Baracod\Larastarterkit\Generator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Nwidart\Modules\Facades\Module;

use function Laravel\Prompts\confirm;

class ControllerGenerator
{
    private string $tableName;

    private string $modelName;

    private string $controllerPath;

    private string $modelNamespace;

    private string $controllerNamespace;

    private ?string $moduleName;

    private ?string $routePath;

    private string $controllerName;

    private ModuleGenerator $module;

    /**
     * ControllerGenerator constructor.
     *
     * @param  string  $table  Le nom de la table.
     * @param  string|null  $modelName  Le nom du modèle.
     * @param  string|null  $moduleName  Le nom du module (optionnel).
     */
    public function __construct(
        string $table,
        ?string $modelName,
        ?string $moduleName = null
    ) {
        $this->tableName = $table;
        $this->modelName = $modelName;
        $this->moduleName = $moduleName;
        $this->controllerName = $this->modelName.'Controller';
        $this->module = new ModuleGenerator($this->moduleName);

        if (empty($this->moduleName)) {
            $this->controllerNamespace = 'App\Http\Controllers';
            $this->modelNamespace = "App\\Models\\{$this->modelName}";
            $this->controllerPath = app_path("Http/Controllers/{$this->controllerName}.php");
        } else {
            $this->controllerNamespace = "Modules\\{$this->moduleName}\\Http\\Controllers";
            $this->modelNamespace = "Modules\\{$this->moduleName}\\Models\\{$this->modelName}";
            $this->controllerPath = base_path("Modules/{$this->moduleName}/app/Http/Controllers/{$this->controllerName}.php");
            $this->routePath = base_path("Modules/{$this->moduleName}/routes/api.php");
        }

        File::ensureDirectoryExists($this->module->getPathControllers());
    }

    /**
     * Génère le contrôleur.
     *
     * @param  array|null  $response  Référence à un tableau de réponse.
     */
    public function generate(?array &$response = null): bool
    {
        // Récupération du template du contrôleur.
        $controllerTemplate = File::get(base_path('stubs/entity-generator/Controller.stub'));

        $requestGenerator = new RequestGenerator($this->tableName, $this->modelName, $this->moduleName);
        $requestGenerator->generate();

        $requestClass = "{$this->modelName}Request";

        // Génération des règles de validation pour les actions "add" et "edit".
        $addValidationRules = $this->generateValidationRules('add');
        $editValidationRules = $this->generateValidationRules('edit');

        // Formatage des règles pour insertion dans le template.
        $formattedAddRules = collect($addValidationRules)
            ->map(fn ($rule, $field) => "'{$field}' => '{$rule}'")
            ->implode(",\n            ");
        $formattedEditRules = collect($editValidationRules)
            ->map(fn ($rule, $field) => "'{$field}' => '{$rule}'")
            ->implode(",\n            ");

        $modelVariable = Str::camel($this->modelName); // Exemple : "post" au lieu de "Post"
        $requestNamespace = $this->module->getRequestNamespace().'\\'.$this->modelName.'Request';

        $controllerContent = str_replace(
            ['{{ namespace }}', '{{ modelNamespace }}', '{{ modelName }}', '{{ controllerName }}', '{{ requestClass }}', '{{ modelVariable }}', '{{ requestNamespace }}'],
            [$this->controllerNamespace, $this->modelNamespace, $this->modelName, $this->controllerName, $requestClass, $modelVariable, $requestNamespace],
            $controllerTemplate
        );

        $confirmation = true;
        // Vérification de l'existence du fichier.
        if (File::exists($this->controllerPath)) {
            $confirmation = confirm("Voulez-vous écraser le controller `{$this->controllerName}`");
        }

        if (! $confirmation) {
            return false;
        }

        // Écriture du fichier du contrôleur.
        File::put($this->controllerPath, $controllerContent);

        // Si un module est défini, on génère la route associée.
        if (! empty($this->moduleName) && $this->routePath) {
            $this->generateRoute();
        }

        $response = [
            'message' => "Le contrôleur `{$this->controllerName}` a été généré avec succès.",
            'path' => $this->controllerPath,
        ];

        return true;
    }

    /**
     * Génère les règles de validation en fonction des colonnes de la table.
     *
     * @param  string  $type  Type de règles ("add" ou "edit").
     */
    private function generateValidationRules(string $type = 'edit'): array
    {
        $columns = DB::select(
            'SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
        FROM INFORMATION_SCHEMA.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?',
            [env('DB_DATABASE'), $this->tableName]
        );

        // Exclure certaines colonnes.
        $columns = array_filter($columns, function ($column) use ($type) {
            if ($column->COLUMN_NAME === 'id' && $type === 'edit') {
                return true;
            }

            return ! in_array($column->COLUMN_NAME, ['id', 'created_at', 'updated_at']);
        });

        if ($type === 'add') {
            // Pour l'ajout, on exclut la colonne "id" si présente.
            $columns = array_filter($columns, function ($column) {
                return $column->COLUMN_NAME !== 'id';
            });
        }

        $foreignKeys = $this->getForeignKeys();
        $rules = [];

        foreach ($columns as $column) {
            $field = $column->COLUMN_NAME;
            $dataType = $column->DATA_TYPE;
            $columnType = $column->COLUMN_TYPE; // Récupérer `tinyint(1)`
            $nullable = $column->IS_NULLABLE === 'YES';
            $maxLength = $column->CHARACTER_MAXIMUM_LENGTH;

            $fieldRules = [];
            $fieldRules[] = $nullable ? 'nullable' : 'required';

            // Vérification si la colonne est une clé étrangère.
            $foreignKey = collect($foreignKeys)->firstWhere('COLUMN_NAME', $field);
            if ($foreignKey) {
                $fieldRules[] = "exists:{$foreignKey->REFERENCED_TABLE_NAME},{$foreignKey->REFERENCED_COLUMN_NAME}";
            } else {
                // Mapping des types SQL vers des règles de validation Laravel.
                switch (true) {
                    case str_contains($columnType, 'tinyint(1)'):
                        $fieldRules[] = 'boolean';
                        break;
                    case str_contains($dataType, 'varchar'):
                    case str_contains($dataType, 'text'):
                        $fieldRules[] = $maxLength ? "string|max:{$maxLength}" : 'string';
                        break;
                    case str_contains($dataType, 'int'):
                    case str_contains($dataType, 'bigint'):
                    case str_contains($dataType, 'smallint'):
                        $fieldRules[] = 'integer';
                        break;
                    case str_contains($dataType, 'decimal'):
                    case str_contains($dataType, 'float'):
                    case str_contains($dataType, 'double'):
                        $fieldRules[] = 'numeric';
                        break;
                    case str_contains($dataType, 'date'):
                        $fieldRules[] = 'date';
                        break;
                    case str_contains($dataType, 'datetime'):
                    case str_contains($dataType, 'timestamp'):
                        $fieldRules[] = 'date_format:Y-m-d H:i:s';
                        break;
                    case str_contains($dataType, 'json'):
                        $fieldRules[] = 'json';
                        break;
                    default:
                        $fieldRules[] = 'string';
                        break;
                }
            }

            $rules[$field] = implode('|', $fieldRules);
        }

        return $rules;
    }

    /**
     * Récupère les clés étrangères pour la table.
     */
    private function getForeignKeys(): array
    {
        return DB::select(
            'SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
            FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [env('DB_DATABASE'), $this->tableName]
        );
    }

    /**
     * Génère une entrée de route pour le contrôleur dans le fichier de routes API du module.
     */
    // public function generateRoute(): void
    // {
    //     try {
    //         // On construit un nom de route à partir du module et du modèle.
    //         $routeName = strtolower("{$this->moduleName}-{$this->modelName}");
    //         $apiFileContent = File::get($this->routePath);

    //         // On détermine le nom de la ressource à partir du nom du modèle (pluriel et en minuscule).
    //         $resourceName =  Str::plural(strtolower($this->modelName));

    //         $callController = '';
    //         $callController .= "Route::delete('{$resourceName}/delete-multiple', ['{$this->controllerNamespace}\\{$this->controllerName}', 'destroyMultiple'])->name('{$routeName}-delete-multiple');";
    //         $callController .= "\nRoute::resource('{$resourceName}', '{$this->controllerNamespace}\\{$this->controllerName}')->names('{$routeName}');";
    //         $callController .= "\n//{{ next-route }}";

    //         $updatedContent = str_replace('//{{ next-route }}', $callController, $apiFileContent);

    //         File::put($this->routePath, $updatedContent);
    //     } catch (\Throwable $th) {
    //         // Vous pouvez ajouter ici une journalisation de l'erreur si nécessaire.
    //     }
    // }

    public function generateRoute(): void
    {
        try {
            $routeName = strtolower("{$this->moduleName}-{$this->modelName}");
            $apiFileContent = File::get($this->routePath);

            // Nom de la ressource au pluriel en minuscule

            $resourceName = Str::smartPlural(strtolower($this->modelName));
            $controllerFullClass = "{$this->controllerNamespace}\\{$this->controllerName}";

            // Déclarations de routes
            $deleteRouteLine = "Route::delete('{$resourceName}/delete-multiple', ['{$controllerFullClass}', 'destroyMultiple'])->name('{$routeName}-delete-multiple');";
            $resourceRouteLine = "Route::resource('{$resourceName}', '{$controllerFullClass}')->names('{$routeName}');";

            // Vérification si les lignes existent déjà
            if (Str::contains($apiFileContent, $deleteRouteLine) && Str::contains($apiFileContent, $resourceRouteLine)) {
                return; // Ne rien faire si les routes existent déjà
            }

            // Ajout des routes à l'emplacement prévu
            $newRoutes = $deleteRouteLine."\n".$resourceRouteLine."\n//{{ next-route }}";
            $updatedContent = str_replace('//{{ next-route }}', $newRoutes, $apiFileContent);

            File::put($this->routePath, $updatedContent);
        } catch (\Throwable $th) {
            // Log optionnel : logger()->error('Erreur lors de la génération des routes', ['exception' => $th]);
        }
    }
}
