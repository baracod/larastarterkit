<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai;

use Baracod\Larastarterkit\Generator\Ai\Agents\EntityDesignAgent;
use Baracod\Larastarterkit\Generator\Ai\Agents\FakeDataAgent;
use Baracod\Larastarterkit\Generator\Ai\Agents\TranslationAgent;
use Baracod\Larastarterkit\Generator\Ai\Agents\ValidationAgent;
use Baracod\Larastarterkit\Generator\Ai\Fallback\FakeData;
use Baracod\Larastarterkit\Generator\Ai\Fallback\FieldSyntax;
use Baracod\Larastarterkit\Generator\Ai\Fallback\Labels;
use Baracod\Larastarterkit\Generator\Ai\Fallback\ValidationHints;
use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Baracod\Larastarterkit\Generator\DefinitionFile\ModelDefinition;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Tâches assistées du générateur. Chaque méthode renvoie un résultat exploitable même sans IA,
 * et toute sortie de l'IA est validée avant usage (elle n'est jamais écrite telle quelle dans du code).
 */
final class Assistant
{
    public function __construct(private readonly GeneratorAi $ai) {}

    /**
     * Libellés i18n d'une entité : {locale: {title, titlePlural, menuTitle, menuDescription, field: {camelKey: label}}}.
     *
     * @param  list<string>  $fields
     * @return AiOutcome<array<string, array<string, mixed>>>
     */
    public function translations(string $entity, string $module, array $fields, ?array $locales = null): AiOutcome
    {
        $locales ??= (array) config('generator.locales', ['fr', 'en']);
        $fallback = Labels::translations($entity, $fields, $locales);

        return $this->ai->attempt(
            'translations',
            function () use ($entity, $module, $fields, $locales, $fallback) {
                $agent = new TranslationAgent($entity, $module, $fields, $locales);

                return $this->mergeTranslations($this->ai->ask($agent, $agent->userPrompt()), $fallback);
            },
            fn () => $fallback,
        );
    }

    /**
     * Conçoit un modèle depuis une description. Sans IA, la description doit utiliser la syntaxe de champs
     * (ex. "name:string:120, email:string:unique").
     *
     * @param  list<string>  $knownTables
     * @return AiOutcome<array{modelName:string,tableName:string,fields:list<FieldDefinition>,relations:list<array{foreignKey:string,table:string}>}>
     */
    public function designEntity(string $module, string $description, ?string $modelName = null, array $knownTables = []): AiOutcome
    {
        $fallback = function () use ($module, $description, $modelName) {
            if ($modelName === null || $modelName === '' || ! self::looksLikeFieldSyntax($description)) {
                // L'IA était disponible mais sa proposition a échoué : c'est cette cause qu'il faut signaler.
                $journal = $this->ai->journal();
                $failure = $this->ai->enabled('design') && $journal !== [] ? ($journal[array_key_last($journal)]['reason'] ?? null) : null;
                throw new InvalidArgumentException($failure !== null
                    ? "L'IA n'a pas fourni de conception exploitable ({$failure}). Reformulez la description ou utilisez la syntaxe « nom:type:modificateurs »."
                    : 'Sans IA, indiquez le nom du modèle et décrivez les champs avec la syntaxe « nom:type:modificateurs » (ex. name:string:120, email:string:unique).');
            }
            $parsed = FieldSyntax::parse($description);

            return [
                'modelName' => Str::studly($modelName),
                'tableName' => self::defaultTable($module, $modelName),
                'fields' => $parsed['fields'],
                'relations' => $parsed['relations'],
            ];
        };

        // Une définition déjà écrite dans la syntaxe compacte n'a pas besoin de l'IA.
        if ($modelName && self::looksLikeFieldSyntax($description)) {
            return new AiOutcome($fallback(), false, 'syntaxe de champs fournie');
        }

        return $this->ai->attempt(
            'design',
            function () use ($module, $description, $modelName, $knownTables) {
                $agent = new EntityDesignAgent($module, $description, $modelName, $knownTables);

                return $this->sanitizeDesign($this->ai->ask($agent, $agent->userPrompt()), $module, $modelName, $knownTables);
            },
            $fallback,
        );
    }

    /**
     * Règles de validation complémentaires par champ (format, bornes). Les heuristiques s'appliquent toujours ;
     * l'IA peut les compléter, dans la limite des règles autorisées.
     *
     * @return AiOutcome<array<string, list<string>>>
     */
    public function validationRules(ModelDefinition $model): AiOutcome
    {
        $heuristics = [];
        foreach ($model->fields() as $field) {
            $heuristics[$field->name] = ValidationHints::for($field);
        }

        return $this->ai->attempt(
            'validation',
            function () use ($model, $heuristics) {
                $described = [];
                foreach ($model->fields() as $field) {
                    $described[$field->name] = $field->type->value
                        .($field->length ? ", longueur {$field->length}" : '')
                        .($field->enumValues ? ', valeurs : '.implode('|', $field->enumValues) : '')
                        .($field->label ? ", « {$field->label} »" : '');
                }
                $agent = new ValidationAgent($model->name(), $described);
                $answer = $this->ai->ask($agent, $agent->userPrompt());

                $rules = $heuristics;
                foreach ((array) ($answer['fields'] ?? []) as $item) {
                    $name = is_array($item) ? ($item['field'] ?? null) : null;
                    if (is_string($name) && isset($rules[$name]) && is_array($item['rules'] ?? null)) {
                        $proposed = ValidationHints::sanitize($item['rules']);
                        // Une borne "max" proposée par l'IA remplace celle de l'heuristique.
                        if (array_filter($proposed, static fn (string $r) => str_starts_with($r, 'max:'))) {
                            $rules[$name] = array_values(array_filter($rules[$name], static fn (string $r) => ! str_starts_with($r, 'max:')));
                        }
                        $rules[$name] = ValidationHints::sanitize([...$rules[$name], ...$proposed]);
                    }
                }

                return $rules;
            },
            fn () => $heuristics,
        );
    }

    /**
     * Formateur Faker retenu pour chaque champ.
     *
     * @return AiOutcome<array<string, string>>
     */
    public function fakeFormatters(ModelDefinition $model): AiOutcome
    {
        $heuristics = [];
        foreach ($model->fields() as $field) {
            $heuristics[$field->name] = FakeData::formatterFor($field, $model->name());
        }

        return $this->ai->attempt(
            'fake_data',
            function () use ($model, $heuristics) {
                $types = array_map(static fn (FieldDefinition $f) => $f->type->value, $model->fields());
                $agent = new FakeDataAgent($model->name(), $types);
                $answer = $this->ai->ask($agent, $agent->userPrompt());

                $formatters = $heuristics;
                foreach ((array) ($answer['fields'] ?? []) as $item) {
                    $name = is_array($item) ? ($item['field'] ?? null) : null;
                    $formatter = is_array($item) ? ($item['formatter'] ?? null) : null;
                    if (is_string($name) && isset($formatters[$name]) && is_string($formatter) && isset(FakeData::FORMATTERS[$formatter])) {
                        $formatters[$name] = $formatter;
                    }
                }

                return $formatters;
            },
            fn () => $heuristics,
        );
    }

    public static function defaultTable(string $module, string $modelName): string
    {
        $table = Str::snake(Str::pluralStudly(Str::studly($modelName)));
        $prefix = Str::snake($module).'_';

        return str_starts_with($table, $prefix) ? $table : $prefix.$table;
    }

    public static function looksLikeFieldSyntax(string $description): bool
    {
        return (bool) preg_match('/^\s*[a-z_][a-z0-9_]*\s*:\s*[a-z]+/i', $description) && ! preg_match('/[.!?]\s|\n/', trim($description));
    }

    /**
     * @param  array<string, mixed>  $answer
     * @param  array<string, array<string, mixed>>  $fallback
     * @return array<string, array<string, mixed>>
     */
    private function mergeTranslations(array $answer, array $fallback): array
    {
        $out = $fallback;
        foreach ($fallback as $locale => $defaults) {
            $proposed = $answer[$locale] ?? null;
            if (! is_array($proposed)) {
                continue;
            }
            foreach (['title', 'titlePlural', 'menuTitle', 'menuDescription'] as $key) {
                if (is_string($proposed[$key] ?? null) && trim($proposed[$key]) !== '') {
                    $out[$locale][$key] = self::cleanLabel($proposed[$key], 160);
                }
            }
            foreach (array_keys($defaults['field']) as $key) {
                $label = $proposed['field'][$key] ?? null;
                if (is_string($label) && trim($label) !== '') {
                    $out[$locale]['field'][$key] = self::cleanLabel($label, 80);
                }
            }
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $answer
     * @param  list<string>  $knownTables
     * @return array{modelName:string,tableName:string,fields:list<FieldDefinition>,relations:list<array{foreignKey:string,table:string}>}
     */
    private function sanitizeDesign(array $answer, string $module, ?string $modelName, array $knownTables): array
    {
        $name = Str::studly((string) ($modelName ?: ($answer['modelName'] ?? '')));
        if (! preg_match('/^[A-Z][A-Za-z0-9]{0,63}$/', $name)) {
            throw new InvalidArgumentException('Nom de modèle proposé invalide.');
        }
        $table = Str::snake((string) ($answer['tableName'] ?? ''));
        if (! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $table)) {
            $table = self::defaultTable($module, $name);
        }

        $technical = (array) config('generator.technical_columns', []);
        $fields = [];
        foreach ((array) ($answer['fields'] ?? []) as $raw) {
            if (! is_array($raw)) {
                continue;
            }
            $fieldName = Str::snake((string) ($raw['name'] ?? ''));
            $type = FieldType::tryFrom((string) ($raw['type'] ?? ''));
            if ($type === null || isset($fields[$fieldName]) || in_array($fieldName, $technical, true) || ! preg_match('/^[a-z][a-z0-9_]{0,63}$/', $fieldName)) {
                continue;
            }
            $enumValues = $type === FieldType::Enum
                ? array_values(array_filter((array) ($raw['enumValues'] ?? []), static fn ($v) => is_string($v) && preg_match('/^[A-Za-z0-9_-]{1,40}$/', $v)))
                : [];
            if ($type === FieldType::Enum && $enumValues === []) {
                $type = FieldType::String;
            }
            $length = is_numeric($raw['length'] ?? null) && $type === FieldType::String ? max(1, min(65535, (int) $raw['length'])) : null;
            $fields[$fieldName] = new FieldDefinition(
                $fieldName,
                $type,
                nullable: (bool) ($raw['nullable'] ?? false),
                unique: (bool) ($raw['unique'] ?? false),
                length: $length,
                enumValues: $enumValues,
                label: is_string($raw['label'] ?? null) ? self::cleanLabel($raw['label'], 80) : null,
            );
        }
        if ($fields === []) {
            throw new InvalidArgumentException('La proposition ne contient aucun champ exploitable.');
        }

        $relations = [];
        foreach ((array) ($answer['relations'] ?? []) as $raw) {
            $foreignKey = is_array($raw) ? (string) ($raw['foreignKey'] ?? '') : '';
            $related = is_array($raw) ? (string) ($raw['table'] ?? '') : '';
            // Seules les relations vers des tables réelles, portées par un champ entier du modèle, sont conservées.
            if (isset($fields[$foreignKey]) && $fields[$foreignKey]->type === FieldType::Integer && ($related === $table || in_array($related, $knownTables, true))) {
                $relations[] = ['foreignKey' => $foreignKey, 'table' => $related];
            }
        }

        return ['modelName' => $name, 'tableName' => $table, 'fields' => array_values($fields), 'relations' => $relations];
    }

    private static function cleanLabel(string $label, int $max): string
    {
        return mb_substr(trim(preg_replace('/\s+/u', ' ', strip_tags($label)) ?? ''), 0, $max);
    }
}
