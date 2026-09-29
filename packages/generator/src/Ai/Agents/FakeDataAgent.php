<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Agents;

use Baracod\Larastarterkit\Generator\Ai\Fallback\FakeData;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Choisit, pour chaque champ, le générateur Faker le plus réaliste parmi une liste fermée.
 */
final class FakeDataAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  array<string, string>  $fields  nom => type
     */
    public function __construct(
        public readonly string $entity,
        public readonly array $fields,
    ) {}

    public function instructions(): string
    {
        return 'Tu prépares des données de démonstration réalistes pour une factory Laravel. '
            .'Pour chaque champ, choisis le formateur le plus plausible compte tenu de son nom, de son type et de l’entité.';
    }

    public function userPrompt(): string
    {
        $lines = [];
        foreach ($this->fields as $name => $type) {
            $lines[] = "- {$name} ({$type})";
        }

        return "Entité « {$this->entity} ».\nChamps :\n".implode("\n", $lines);
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'fields' => $schema->array()->items($schema->object([
                'field' => $schema->string()->enum(array_keys($this->fields))->required(),
                'formatter' => $schema->string()->enum(array_keys(FakeData::FORMATTERS))->required(),
            ])->withoutAdditionalProperties())->required(),
        ];
    }
}
