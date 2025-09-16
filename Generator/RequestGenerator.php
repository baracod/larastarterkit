<?php

namespace App\Generator;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class RequestGenerator
{
    private string $tableName;
    private string $modelName;
    private string $moduleName;
    private string $requestNamespace;
    private string $requestPath;

    public function __construct(string $tableName, string $modelName, ?string $moduleName = null)
    {


        $this->tableName = $tableName;
        $this->modelName = $modelName;
        $this->moduleName = $moduleName;

        if ($moduleName) {
            $this->requestNamespace = "Modules\\{$moduleName}\\Http\\Requests";
            $this->requestPath = base_path("Modules/{$moduleName}/app/Http/Requests/{$this->modelName}Request.php");
        } else {
            $this->requestNamespace = "App\\Http\\Requests";
            $this->requestPath = app_path("Http/Requests/{$this->modelName}Request.php");
        }
    }

    public function generate(): bool
    {
        // Récupération des règles de validation
        $validationRules = $this->generateValidationRules();
        $validationRules = $this->formatValidationRules($validationRules);

        $errorMessages = $this->generateMessages();

        // Vérifier si le fichier existe déjà
        // if (File::exists($this->requestPath)) {
        //     return false;
        // }

        // Charger le stub du fichier Request 
        $stubPath = base_path('stubs/entity-generator/Request.stub');
        if (!File::exists($stubPath)) {
            throw new \Exception("Le fichier stub `stubs/entity-generator/Request.stub` est introuvable.");
        }

        $stubContent = File::get($stubPath);

        // Remplacement des valeurs dynamiques
        $requestContent = str_replace(
            ['{{ namespace }}', '{{ modelName }}', '{{ validationRules }}', '{{ errorMessages }}'],
            [$this->requestNamespace, $this->modelName, $validationRules, $errorMessages],
            $stubContent
        );

        // Créer le fichier Request
        File::ensureDirectoryExists(dirname($this->requestPath));
        File::put($this->requestPath, $requestContent);


        return true;
    }

    protected function formatValidationRules(array $rules): string
    {
        // Prépare chaque ligne sous la forme : 'champ' => 'règle1|règle2',
        $lines = array_map(
            fn($key, $value) => "    '{$key}' => {$value},",
            array_keys($rules),
            $rules
        );

        // Assemble toutes les lignes dans un bloc de tableau PHP
        $content = implode("\n", $lines);

        // Retourne le tableau complet prêt à coller dans la méthode rules()
        return "[\n{$content}\n];";
    }

    /**
     * Génère les règles de validation en fonction des colonnes de la table.
     */
    private function generateValidationRules(): array
    {
        $columns = DB::select(
            "SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, CHARACTER_MAXIMUM_LENGTH
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ?",
            [env('DB_DATABASE'), $this->tableName]
        );

        $foreignKeys = DB::select(
            "SELECT COLUMN_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
         FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
         WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL",
            [env('DB_DATABASE'), $this->tableName]
        );

        $rules = [];

        foreach ($columns as $column) {
            $field       = $column->COLUMN_NAME;
            $dataType    = $column->DATA_TYPE;
            $columnType  = $column->COLUMN_TYPE;
            $isNullable  = strtoupper((string) $column->IS_NULLABLE) === 'YES';
            $maxLength   = $column->CHARACTER_MAXIMUM_LENGTH;

            // ✅ Forcer nullable si nom = id | hash | uuid (insensible à la casse)
            $forceNullableByName = in_array(strtolower($field), ['id', 'hash', 'uuid'], true);
            $nullable            = $isNullable || $forceNullableByName;

            $fieldRules = [];
            $fieldRules[] = $nullable ? 'nullable' : 'required';

            $foreignKey = collect($foreignKeys)->firstWhere('COLUMN_NAME', $field);
            if ($foreignKey) {
                // clé étrangère -> exists sur table/référence
                $fieldRules[] = "exists:{$foreignKey->REFERENCED_TABLE_NAME},{$foreignKey->REFERENCED_COLUMN_NAME}";
            } else {
                switch (true) {
                    case str_contains($columnType, 'tinyint(1)'):
                        $fieldRules[] = 'boolean';
                        break;

                    case str_contains($dataType, 'varchar'):
                    case str_contains($dataType, 'text'):
                        $fieldRules[] = $maxLength ? "string|max:{$maxLength}" : 'string';
                        break;

                    case str_contains($dataType, 'bigint'):
                    case str_contains($dataType, 'int'):
                    case str_contains($dataType, 'smallint'):
                        $fieldRules[] = 'integer';
                        break;

                    case str_contains($dataType, 'decimal'):
                    case str_contains($dataType, 'float'):
                    case str_contains($dataType, 'double'):
                        $fieldRules[] = 'numeric';
                        break;

                    case str_contains($dataType, 'datetime'):
                    case str_contains($dataType, 'timestamp'):
                        // garde ton format strict si souhaité
                        $fieldRules[] = 'date_format:Y-m-d\TH:i:s.u\Z|before:now';
                        break;

                    case str_contains($dataType, 'date'):
                        $fieldRules[] = 'date';
                        break;

                    case str_contains($dataType, 'json'):
                        $fieldRules[] = 'json';
                        break;

                    default:
                        $fieldRules[] = 'string';
                        break;
                }
            }

            // Conserver ton format de sortie (chaîne joinée)
            $rules[$field] = "'" . implode('|', $fieldRules) . "'";
        }

        return $rules;
    }


    protected function generateMessages(): string
    {
        $rulesByColumn = $this->generateValidationRules(); // ['champ' => 'required|string|max:255', ...]
        // dd($rulesByColumn);
        $messages = [];

        foreach ($rulesByColumn as $name => $ruleString) {
            $rules = is_array($ruleString) ? $ruleString : explode('|', $ruleString);


            foreach ($rules as $rule) {
                $ruleName = strtolower(trim($rule));
                $ruleName = explode(':', $ruleName)[0];
                $ruleName = trim($ruleName, "'\" "); //

                switch ($ruleName) {
                    case 'required':
                        $messages[] = "            '$name.required' => 'Le champ $name est obligatoire.',";
                        break;
                    case 'nullable':
                        // pas de message, mais on ne l’ignore plus pour les autres règles
                        break;
                    case 'email':
                        $messages[] = "            '$name.email' => 'Le champ $name doit être une adresse email valide.',";
                        break;
                    case 'string':
                        $messages[] = "            '$name.string' => 'Le champ $name doit être une chaîne de caractères.',";
                        break;
                    case 'max':
                        $messages[] = "            '$name.max' => 'Le champ $name dépasse la longueur maximale autorisée.',";
                        break;
                    case 'min':
                        $messages[] = "            '$name.min' => 'Le champ $name est trop court.',";
                        break;
                    case 'integer':
                        $messages[] = "            '$name.integer' => 'Le champ $name doit être un entier.',";
                        break;
                    case 'numeric':
                        $messages[] = "            '$name.numeric' => 'Le champ $name doit être un nombre.',";
                        break;
                    case 'date':
                        $messages[] = "            '$name.date' => 'Le champ $name doit être une date valide.',";
                        break;
                    case 'boolean':
                        $messages[] = "            '$name.boolean' => 'Le champ $name doit être vrai ou faux.',";
                        break;
                    case 'unique':
                        $messages[] = "            '$name.unique' => 'Le champ $name doit être unique.',";
                        break;
                    case 'exists':
                        $messages[] = "            '$name.exists' => 'La valeur du champ $name est invalide.',";
                        break;
                    case 'confirmed':
                        $messages[] = "            '$name.confirmed' => 'La confirmation du champ $name ne correspond pas.',";
                        break;
                    case 'date_format':
                        $messages[] = "            '$name.date_format' => 'Le champ $name doit respecter le format requis.',";
                        break;
                    case 'before':
                        $messages[] = "            '$name.before' => 'Le champ $name doit être une date antérieure.',";
                        break;
                    default:
                        // Ne rien faire pour les règles inconnues
                        break;
                }
            }
        }

        $body = implode("\n", array_unique($messages));

        return <<<PHP
public function messages(): array
{
    return [
$body
    ];
}
PHP;
    }
}
