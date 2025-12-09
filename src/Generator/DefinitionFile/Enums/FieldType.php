<?php

declare(strict_types=1);

namespace Baracod\Larastarterkit\Generator\DefinitionFile\Enums;

/**
 * Enum FieldType
 *
 * Typage fort des champs "fillable". Permet d’éviter les valeurs libres
 * et d’améliorer l’autocomplétion/validation statique.
 *
 * @method string value()
 */
enum FieldType: string
{
    case String = 'string';
    case Text = 'text';
    case Integer = 'integer';
    case Float = 'float';
    case Boolean = 'boolean';
    case Date = 'date';
    case DateTime = 'datetime';
    case Json = 'json';
}
