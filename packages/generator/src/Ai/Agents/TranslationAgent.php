<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Agents;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasStructuredOutput;
use Laravel\Ai\Promptable;

/**
 * Libellés d'interface (titres, menu, champs) d'une entité dans chaque langue demandée.
 */
final class TranslationAgent implements Agent, HasStructuredOutput
{
    use Promptable;

    /**
     * @param  list<string>  $fields  Noms de colonnes (snake_case)
     * @param  list<string>  $locales
     */
    public function __construct(
        public readonly string $entity,
        public readonly string $module,
        public readonly array $fields,
        public readonly array $locales,
    ) {}

    public function instructions(): string
    {
        return 'Tu rédiges les libellés d’interface d’une application de gestion (Vue + Vuetify). '
            .'Les libellés sont courts, naturels, sans code technique ni mise en forme, adaptés à des utilisateurs métier. '
            .'Pour chaque langue, respecte la grammaire et les usages de la langue cible. '
            .'Les clés de champs sont fournies en camelCase et doivent être conservées telles quelles.';
    }

    public function userPrompt(): string
    {
        $fields = implode(', ', array_map(static fn (string $f) => Str::camel($f)." (colonne {$f})", $this->fields));

        return "Entité « {$this->entity} » du module « {$this->module} ».\n"
            .'Langues : '.implode(', ', $this->locales).".\n"
            ."Champs : {$fields}.\n"
            .'Fournis : title (singulier), titlePlural, menuTitle, menuDescription (une phrase) et un libellé par champ.';
    }

    public function schema(JsonSchema $schema): array
    {
        $fieldProperties = [];
        foreach ($this->fields as $field) {
            $fieldProperties[Str::camel($field)] = $schema->string()->required();
        }

        $locale = fn () => $schema->object([
            'title' => $schema->string()->required(),
            'titlePlural' => $schema->string()->required(),
            'menuTitle' => $schema->string()->required(),
            'menuDescription' => $schema->string()->required(),
            'field' => $schema->object($fieldProperties)->withoutAdditionalProperties()->required(),
        ])->withoutAdditionalProperties()->required();

        return array_combine($this->locales, array_map(static fn () => $locale(), $this->locales));
    }
}
