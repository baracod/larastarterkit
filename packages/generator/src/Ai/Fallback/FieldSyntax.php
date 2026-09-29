<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Ai\Fallback;

use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Illuminate\Support\Str;
use InvalidArgumentException;

/**
 * Syntaxe compacte de définition de champs, utilisable sans IA :
 *
 *   name:string:120, email:string:unique, bio:text:nullable,
 *   status:enum(draft|published):default=draft, price:float, category_id:fk(shop_categories), country_code:fk(countries.code)
 *
 * Modificateurs : nullable, unique, <longueur>, default=<valeur>, label=<libellé>.
 */
final class FieldSyntax
{
    /**
     * @return array{fields:list<FieldDefinition>,relations:list<array{foreignKey:string,table:string}>}
     */
    public static function parse(string $definition): array
    {
        $fields = [];
        $relations = [];
        foreach (self::split($definition) as $chunk) {
            $parts = array_map('trim', explode(':', $chunk));
            $name = Str::snake(array_shift($parts));
            $typeToken = strtolower(array_shift($parts) ?? 'string');
            $enumValues = [];
            $foreignTable = null;
            $ownerKey = 'id';

            if (preg_match('/^enum\((.+)\)$/', $typeToken, $m)) {
                $type = FieldType::Enum;
                $enumValues = array_values(array_filter(array_map('trim', explode('|', $m[1]))));
            } elseif (preg_match('/^fk\(([a-z0-9_]+)(?:\.([a-z0-9_]+))?\)$/', $typeToken, $m)) {
                // fk(table) référence "id" (entier) ; fk(table.colonne) une autre colonne (chaîne).
                $ownerKey = ($m[2] ?? '') !== '' ? $m[2] : 'id';
                $type = $ownerKey === 'id' ? FieldType::Integer : FieldType::String;
                $foreignTable = $m[1];
            } else {
                $type = FieldType::tryFrom(self::alias($typeToken))
                    ?? throw new InvalidArgumentException("Type inconnu « {$typeToken} » pour « {$name} ». Types : ".implode(', ', array_column(FieldType::cases(), 'value')).', enum(a|b), fk(table).');
            }

            $field = new FieldDefinition($name, $type, enumValues: $enumValues, nullable: false);
            foreach ($parts as $modifier) {
                match (true) {
                    $modifier === 'nullable' => $field->nullable(),
                    $modifier === 'unique' => $field->unique(),
                    ctype_digit($modifier) => $field->length((int) $modifier),
                    str_starts_with($modifier, 'default=') => $field->default(substr($modifier, 8)),
                    str_starts_with($modifier, 'label=') => $field->label = substr($modifier, 6),
                    $modifier === '' => null,
                    default => throw new InvalidArgumentException("Modificateur inconnu « {$modifier} » pour « {$name} »."),
                };
            }
            $fields[] = $field;
            if ($foreignTable !== null) {
                $relations[] = ['foreignKey' => $name, 'table' => $foreignTable, 'ownerKey' => $ownerKey];
            }
        }

        if ($fields === []) {
            throw new InvalidArgumentException('Aucun champ défini.');
        }

        return ['fields' => $fields, 'relations' => $relations];
    }

    /** @return list<string> */
    private static function split(string $definition): array
    {
        // Les virgules à l'intérieur de enum(...) ne séparent pas les champs.
        return array_values(array_filter(array_map('trim', preg_split('/,(?![^(]*\))/', $definition) ?: []), static fn (string $c) => $c !== ''));
    }

    private static function alias(string $type): string
    {
        return match ($type) {
            'int', 'bigint' => 'integer',
            'bool' => 'boolean',
            'decimal', 'double', 'number' => 'float',
            'timestamp' => 'datetime',
            'longtext' => 'text',
            'array', 'object' => 'json',
            default => $type,
        };
    }
}
