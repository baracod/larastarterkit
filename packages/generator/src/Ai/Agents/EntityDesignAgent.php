<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Agents;

use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Conçoit un modèle (champs, types, contraintes, relations) à partir d'une description métier.
 */
final class EntityDesignAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $knownTables  Tables existantes utilisables pour les clés étrangères
     */
    public function __construct(
        public readonly string $module,
        public readonly string $description,
        public readonly ?string $modelName = null,
        public readonly array $knownTables = [],
    ) {}

    public function instructions(): string
    {
        return 'Tu es un architecte Laravel. Tu conçois le schéma d’une table métier à partir d’une description. '
            .'Règles : noms de champs en snake_case anglais ; pas de id, created_at, updated_at ni deleted_at (ajoutés automatiquement) ; '
            .'clés étrangères nommées <entité>_id de type integer, uniquement vers les tables fournies ; '
            .'enum uniquement pour une liste fermée de valeurs (enumValues en snake_case) ; '
            .'length seulement pour les chaînes (sinon null) ; label = libellé français court. '
            .'Préfère des champs requis (nullable=false) sauf si la description les rend facultatifs.';
    }

    public function userPrompt(): string
    {
        $tables = $this->knownTables === [] ? 'aucune' : implode(', ', $this->knownTables);

        return "Module : {$this->module}\n"
            .($this->modelName ? "Nom du modèle souhaité : {$this->modelName}\n" : '')
            ."Tables existantes : {$tables}\n"
            ."Description :\n{$this->description}";
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'modelName' => $schema->string()->description('Nom de classe StudlyCase au singulier, en anglais')->required(),
            'tableName' => $schema->string()->description('Nom de table snake_case au pluriel')->required(),
            'fields' => $schema->array()->items($schema->object([
                'name' => $schema->string()->required(),
                'type' => $schema->string()->enum(array_column(FieldType::cases(), 'value'))->required(),
                'nullable' => $schema->boolean()->required(),
                'unique' => $schema->boolean()->required(),
                'length' => $schema->integer()->nullable()->required(),
                'enumValues' => $schema->array()->items($schema->string())->required(),
                'label' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
            'relations' => $schema->array()->items($schema->object([
                'foreignKey' => $schema->string()->required(),
                'table' => $schema->string()->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
