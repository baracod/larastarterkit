<?php

namespace Baracod\Larastarterkit\Generator\Traits;


trait SqlConversion
{
    /**
     * Retourne une catégorie générique à partir d'un type SQL MariaDB.
     * Catégories: number | boolean | text | longText | date | binary | list | unknown
     */
    public static function map(string $sqlType): string
    {
        $type = strtolower(trim($sqlType));
        $base = preg_replace('/\(.*/', '', $type); // varchar(255) -> varchar, enum('a','b') -> enum

        // tinyint(1) souvent utilisé comme booléen
        if (preg_match('/^tinyint\s*\(\s*1\s*\)/', $type)) {
            return 'boolean';
        }

        // enum / set / json sont des listes
        if (in_array($base, ['enum', 'set'], true)) {
            return 'list';
        }
        if ($base === 'json') {
            return 'list';
        }

        return match ($base) {
            // numériques
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint',
            'decimal', 'dec', 'numeric', 'fixed', 'float', 'double', 'real' => 'number',

            // booléens explicites
            'boolean', 'bool' => 'boolean',

            // texte court
            'char', 'varchar', 'string' => 'text',

            // texte long
            'text', 'tinytext', 'mediumtext', 'longtext' => 'longText',

            // date/heure
            'date', 'datetime', 'timestamp', 'time', 'year' => 'date',

            // binaire
            'blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary' => 'binary',

            default => 'unknown',
        };
    }

    /**
     * Si type = enum('A','B') ou set('X','Y'), renvoie la liste des valeurs.
     * Sinon renvoie null.
     *
     * @return list<string>|null
     */
    public static function enumValues(string $sqlType): ?array
    {
        $type = strtolower(trim($sqlType));
        if (! preg_match('/^(enum|set)\s*\((.*)\)$/', $type, $m)) {
            return null;
        }

        $inside = $m[2]; // 'a','b','c'
        // Capture les valeurs entre quotes simples, en gérant l'échappement \'
        preg_match_all("/'((?:\\\\'|[^'])*)'/", $inside, $vals);

        // Remplace \' par '
        return array_map(static fn ($v) => str_replace("\\'", "'", $v), $vals[1]);
    }

    /**
     * Convertit un type SQL (MariaDB/MySQL) en type TypeScript.
     *
     * Options supportées :
     * - nullable: bool                => ajoute " | null" (défaut: false)
     * - jsonAs: 'any'|'unknown'|'record'  (Record<string, unknown>) (défaut: 'unknown')
     * - dateAs: 'string'|'date'           (défaut: 'string')
     * - bigIntAs: 'number'|'bigint'       (défaut: 'number')
     * - enumAsUnion: bool                  ('a'|'b' au lieu de string) (défaut: true)
     */
    protected function sqlToTs(string $sqlTypeRaw, array $options = []): string
    {
        $opts = array_merge([
            'nullable' => false,
            'jsonAs' => 'unknown', // 'any' | 'unknown' | 'record'
            'dateAs' => 'string',  // 'string' | 'date'
            'bigIntAs' => 'number',  // 'number' | 'bigint'
            'enumAsUnion' => true,
        ], $options);

        $t = strtolower(trim($sqlTypeRaw));
        $base = preg_replace('/\(.*/', '', $t);                // varchar(255) -> varchar
        $isTinyInt1 = (bool) preg_match('/^tinyint\s*\(\s*1\s*\)/', $t);

        $withNull = static fn (string $ts) => $opts['nullable'] ? "{$ts} | null" : $ts;

        // Enum / Set -> union de littéraux (ou string si enumAsUnion=false)
        $enumVals = $this->extractEnumOrSetValues($t);
        if ($enumVals !== null) {
            $ts = $opts['enumAsUnion'] && count($enumVals) > 0
                ? implode(' | ', array_map(
                    static fn (string $v) => "'".str_replace("'", "\\'", $v)."'",
                    $enumVals
                ))
                : 'string';

            return $withNull($ts);
        }

        // tinyint(1) -> boolean
        if ($isTinyInt1) {
            return $withNull('boolean');
        }

        // JSON flavor
        $jsonType = match ($opts['jsonAs']) {
            'any' => 'any',
            'record' => 'Record<string, unknown>',
            default => 'unknown',
        };

        // Dates flavor
        $dateType = $opts['dateAs'] === 'date' ? 'Date' : 'string';

        // Bigint flavor
        $bigIntType = $opts['bigIntAs'] === 'bigint' ? 'bigint' : 'number';

        // Mapping principal
        $tsType = match ($base) {
            // Entiers
            'tinyint', 'smallint', 'mediumint', 'int', 'integer' => 'number',
            'bigint' => $bigIntType,

            // Décimaux
            'decimal', 'dec', 'numeric', 'fixed', 'float', 'double', 'real' => 'number',

            // Booléens explicites
            'boolean', 'bool' => 'boolean',

            // Textes
            'char', 'varchar', 'string', 'text', 'tinytext', 'mediumtext', 'longtext' => 'string',

            // Dates / temps
            'date', 'datetime', 'timestamp', 'time', 'year' => $dateType,

            // Binaires
            'blob', 'tinyblob', 'mediumblob', 'longblob', 'binary', 'varbinary' => 'Uint8Array', // ou 'string' (base64) selon ton API

            // JSON
            'json' => $jsonType,

            // fallback (enum/set déjà gérés)
            default => 'unknown',
        };

        return $withNull($tsType);
    }

    /**
     * Extrait les valeurs d'un type enum('a','b') ou set('x','y').
     * Renvoie null si le type n'est pas enum/set.
     *
     * @return list<string>|null
     */
    private function extractEnumOrSetValues(string $lowerSqlType): ?array
    {
        if (! preg_match('/^(enum|set)\s*\((.*)\)$/i', $lowerSqlType, $m)) {
            return null;
        }

        $inside = $m[2]; // contenu entre parenthèses
        $vals = [];
        // capture 'val' en gérant l'échappement \'
        preg_match_all("/'((?:\\\\'|[^'])*)'/", $inside, $matches);
        foreach ($matches[1] as $v) {
            $vals[] = str_replace("\\'", "'", $v);
        }

        return $vals;
    }

    public static function sqlToPhpType(string $sqlType): string
    {
        $type = strtolower(trim($sqlType));
        $base = preg_replace('/\(.*/', '', $type);

        // tinyint(1) -> bool
        if (preg_match('/^tinyint\s*\(\s*1\s*\)/', $type)) {
            return 'bool';
        }

        return match ($base) {
            // Entiers
            'tinyint', 'smallint', 'mediumint', 'int', 'integer', 'bigint' => 'int',

            // Décimaux
            'decimal', 'dec', 'numeric', 'fixed', 'float', 'double', 'real' => 'float',

            // Booléens
            'boolean', 'bool' => 'bool',

            // Textes
            'char', 'varchar', 'string', 'text',
            'tinytext', 'mediumtext', 'longtext' => 'string',

            // Dates
            'date', 'datetime', 'timestamp', 'time', 'year' => '\DateTimeInterface',

            // Binaires
            'blob', 'tinyblob', 'mediumblob', 'longblob',
            'binary', 'varbinary' => 'string',

            // JSON / ENUM / SET -> tableau PHP
            'json', 'enum', 'set' => 'array',

            default => 'mixed',
        };
    }
}
