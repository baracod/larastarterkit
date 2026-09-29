<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\Support;

use Baracod\Larastarterkit\Generator\DefinitionFile\Enums\FieldType;
use Baracod\Larastarterkit\Generator\DefinitionFile\FieldDefinition;
use Baracod\Larastarterkit\Generator\Traits\SqlConversion;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

/**
 * Lit une table existante : types, nullabilité réelle, valeurs par défaut, index uniques,
 * valeurs d'énumération et clés étrangères.
 */
final class TableImporter
{
    /**
     * @return array{fields:list<FieldDefinition>, relations:list<array{foreignKey:string,table:string,ownerKey?:string}>, softDeletes:bool, timestamps:bool}
     */
    public static function import(string $table): array
    {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("La table « {$table} » n'existe pas.");
        }

        $unique = [];
        foreach (Schema::getIndexes($table) as $index) {
            if (($index['unique'] ?? false) && ! ($index['primary'] ?? false) && count($index['columns']) === 1) {
                $unique[$index['columns'][0]] = true;
            }
        }
        $relations = [];
        foreach (Schema::getForeignKeys($table) as $foreign) {
            if (count($foreign['columns']) === 1) {
                $relations[] = ['foreignKey' => $foreign['columns'][0], 'table' => $foreign['foreign_table'], 'ownerKey' => $foreign['foreign_columns'][0] ?? 'id'];
            }
        }

        $columns = Schema::getColumns($table);
        $names = array_column($columns, 'name');
        $technical = (array) config('generator.technical_columns', []);
        $fields = [];
        foreach ($columns as $column) {
            if (in_array($column['name'], $technical, true) || ($column['auto_increment'] ?? false)) {
                continue;
            }
            $rawType = (string) ($column['type'] ?? $column['type_name']);
            $sqlType = strtolower($rawType);
            $enumValues = str_starts_with($sqlType, 'enum(') ? (SqlConversion::enumValues($rawType) ?? []) : [];
            $type = $enumValues !== [] ? FieldType::Enum : self::fieldType((string) $column['type_name'], $sqlType);
            $length = $type === FieldType::String && preg_match('/^(?:var)?char\((\d+)\)/', $sqlType, $m) ? (int) $m[1] : null;
            $default = $column['default'] ?? null;
            if (is_string($default)) {
                $default = trim($default, "'");
                $default = strtoupper($default) === 'NULL' ? null : $default;
            }

            $fields[] = new FieldDefinition(
                $column['name'],
                $type,
                defaultValue: $default,
                nullable: (bool) ($column['nullable'] ?? false),
                unique: isset($unique[$column['name']]),
                length: $length,
                enumValues: $enumValues,
            );
        }

        return [
            'fields' => $fields,
            'relations' => $relations,
            'softDeletes' => in_array('deleted_at', $names, true),
            'timestamps' => in_array('created_at', $names, true) && in_array('updated_at', $names, true),
        ];
    }

    private static function fieldType(string $typeName, string $sqlType): FieldType
    {
        $typeName = strtolower($typeName);

        return match (true) {
            $sqlType === 'tinyint(1)' || in_array($typeName, ['bool', 'boolean'], true) => FieldType::Boolean,
            in_array($typeName, ['int', 'integer', 'bigint', 'smallint', 'mediumint', 'tinyint', 'int4', 'int8', 'int2'], true) => FieldType::Integer,
            in_array($typeName, ['decimal', 'numeric', 'float', 'double', 'real', 'float4', 'float8'], true) => FieldType::Float,
            in_array($typeName, ['text', 'mediumtext', 'longtext', 'tinytext'], true) => FieldType::Text,
            $typeName === 'date' => FieldType::Date,
            in_array($typeName, ['datetime', 'timestamp', 'timestamptz'], true) => FieldType::DateTime,
            in_array($typeName, ['json', 'jsonb'], true) => FieldType::Json,
            $typeName === 'uuid' || $sqlType === 'char(36)' => FieldType::Uuid,
            default => FieldType::String,
        };
    }
}
