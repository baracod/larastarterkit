<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Agents;

use Baracod\Larastarterkit\Generator\Ai\Fallback\ValidationHints;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Propose des règles de validation métier complémentaires (format, bornes) pour chaque champ.
 */
final class ValidationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<string, string>  $fields  nom => description (type, contraintes connues)
     */
    public function __construct(
        public readonly string $entity,
        public readonly array $fields,
    ) {}

    public function instructions(): string
    {
        return 'Tu complètes les règles de validation Laravel d’un formulaire. '
            .'Les règles required/nullable, de type, unique et exists sont déjà gérées : ne propose que des contraintes de format ou de bornes. '
            .'Règles autorisées uniquement : '.implode(', ', ValidationHints::ALLOWED).'. '
            .'Paramètres simples (ex. max:120, min:0, digits:10) ; regex seulement si indispensable, délimitée par des slashes. '
            .'Liste vide pour un champ sans contrainte particulière.';
    }

    public function userPrompt(): string
    {
        $lines = [];
        foreach ($this->fields as $name => $description) {
            $lines[] = "- {$name} : {$description}";
        }

        return "Entité « {$this->entity} ».\nChamps :\n".implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fields' => $schema->array()->items($schema->object([
                'field' => $schema->string()->enum(array_keys($this->fields))->required(),
                'rules' => $schema->array()->items($schema->string())->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
