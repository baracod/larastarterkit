<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Fallback;

use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;

/**
 * Règles de validation complémentaires déduites du nom et du type d'un champ, sans IA.
 */
final class ValidationHints
{
    /**
     * Seules ces règles sont acceptées, qu'elles viennent de l'IA ou des heuristiques.
     * Les paramètres éventuels sont eux-mêmes validés (voir sanitize()).
     */
    public const ALLOWED = ['email', 'url', 'ip', 'uuid', 'alpha', 'alpha_dash', 'alpha_num', 'numeric', 'integer', 'min', 'max', 'digits', 'digits_between', 'regex', 'date', 'after_or_equal', 'before_or_equal', 'after', 'before', 'lowercase', 'uppercase', 'starts_with', 'ends_with', 'timezone', 'json', 'boolean', 'string'];

    /** @return list<string> */
    public static function for(FieldDefinition $field): array
    {
        $name = strtolower($field->name);
        $numeric = in_array($field->type, [FieldType::Integer, FieldType::Float], true);

        // Les formats déduits du nom (email, url, téléphone…) ne concernent que les chaînes courtes :
        // un booléen "email_verified" ou un texte "email_body" ne doivent pas les recevoir.
        if ($field->type !== FieldType::String) {
            return $numeric && in_array($name, ['price', 'amount', 'quantity', 'total', 'weight', 'stock'], true) ? ['min:0'] : [];
        }

        $rules = match (true) {
            str_contains($name, 'email') => ['email'],
            in_array($name, ['url', 'website', 'site', 'link'], true) || str_ends_with($name, '_url') => ['url'],
            str_contains($name, 'phone') || str_contains($name, 'mobile') => ['max:30', 'regex:/^[0-9+().\s-]{6,30}$/'],
            $name === 'slug' => ['alpha_dash'],
            $name === 'ip' || str_ends_with($name, '_ip') => ['ip'],
            $name === 'timezone' => ['timezone'],
            default => [],
        };

        if (! array_filter($rules, static fn (string $r) => str_starts_with($r, 'max:'))) {
            $rules[] = 'max:'.($field->length ?? 255);
        }

        return $rules;
    }

    /**
     * Filtre une liste de règles proposée (IA ou utilisateur) : règles connues, paramètres sûrs uniquement.
     *
     * @param  list<mixed>  $rules
     * @return list<string>
     */
    public static function sanitize(array $rules): array
    {
        $safe = [];
        foreach ($rules as $rule) {
            if (! is_string($rule) || strlen($rule) > 120) {
                continue;
            }
            [$name, $parameters] = array_pad(explode(':', $rule, 2), 2, null);
            if (! in_array($name, self::ALLOWED, true)) {
                continue;
            }
            if ($name === 'regex') {
                // Expression délimitée par des slashes, compilable, sans pipe (séparateur de règles Laravel).
                if ($parameters === null || ! preg_match('#^/.+/[imsxu]*$#', $parameters) || str_contains($parameters, '|') || @preg_match($parameters, '') === false) {
                    continue;
                }
            } elseif ($parameters !== null && ! preg_match('/^[A-Za-z0-9_.,\- ]{1,60}$/', $parameters)) {
                continue;
            }
            $safe[] = $rule;
        }

        return array_values(array_unique($safe));
    }
}
